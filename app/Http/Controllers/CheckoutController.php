<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Order;
use App\Support\Cart;
use App\Support\PayPalCheckout;
use App\Support\StripeCheckout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

class CheckoutController extends Controller
{
    public function __construct(
        protected StripeCheckout $stripe,
        protected PayPalCheckout $paypal,
    ) {}

    /**
     * Review the order and collect contact details.
     */
    public function show(Cart $cart): View
    {
        // An empty bag gets its own page rather than a redirect to the
        // builder, which made the two pages look like the same thing.
        if ($cart->isEmpty()) {
            return view('checkout.empty');
        }

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotalCents' => $cart->subtotalCents(),
            'shippingCents' => $cart->shippingCents(),
            'totalCents' => $cart->totalCents(),
            'totalBalls' => $cart->totalBalls(),
            'stripeEnabled' => $this->stripeConfigured(),
            'paypalEnabled' => $this->paypal->isConfigured(),
            'shippingCountries' => config('ballpit.shipping_countries'),
            'regionLabel' => $this->regionLabel(),
        ]);
    }

    /**
     * Create the pending order and hand the customer to their chosen provider.
     */
    public function store(StoreCheckoutRequest $request, Cart $cart): RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()
                ->route('order.create')
                ->with('status', 'Your bag is empty — pick some balls to get started.');
        }

        $provider = $request->provider();

        if (! $this->isEnabled($provider)) {
            return back()->with('error', $this->notConfiguredMessage($provider));
        }

        $order = $this->createPendingOrder($request, $cart, $provider);

        return match ($provider) {
            PaymentProvider::Stripe => $this->redirectToStripe($order),
            PaymentProvider::PayPal => $this->redirectToPayPal($order),
        };
    }

    /**
     * Stripe sends the customer back here after a successful payment.
     */
    public function success(Request $request, Cart $cart): View|RedirectResponse
    {
        $sessionId = $request->string('session_id')->toString();

        if ($sessionId === '') {
            return redirect()->route('home');
        }

        $order = Order::query()
            ->with('items')
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();

        if (! $order) {
            return redirect()->route('home');
        }

        // The webhook is the source of truth, but confirm here too so the
        // customer sees an accurate status immediately after paying.
        if ($order->status !== OrderStatus::Paid && $this->stripeConfigured()) {
            try {
                $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

                if ($session->payment_status === 'paid') {
                    $order->markPaid(is_string($session->payment_intent) ? $session->payment_intent : null);
                }
            } catch (ApiErrorException $exception) {
                Log::warning('Could not confirm Stripe Checkout session.', [
                    'session_id' => $sessionId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $cart->clear();

        return view('checkout.success', [
            'order' => $order->fresh('items'),
        ]);
    }

    /**
     * Stripe sends the customer back here if they abandon the payment page.
     */
    public function cancel(): RedirectResponse
    {
        return redirect()
            ->route('checkout.show')
            ->with('status', 'No payment was taken — your bag is still here when you are ready.');
    }

    /**
     * PayPal redirects here once the buyer approves. The capture happens now
     * so the customer sees a final answer, and the webhook covers the case
     * where they never make it back.
     */
    public function paypalReturn(Request $request, Cart $cart): View|RedirectResponse
    {
        // PayPal appends its order id as `token`.
        $paypalOrderId = $request->string('token')->toString();

        if ($paypalOrderId === '') {
            return redirect()->route('home');
        }

        $order = Order::query()
            ->with('items')
            ->where('paypal_order_id', $paypalOrderId)
            ->first();

        if (! $order) {
            return redirect()->route('home');
        }

        $this->paypal->capture($order);

        $cart->clear();

        return view('checkout.success', [
            'order' => $order->fresh('items'),
        ]);
    }

    public function paypalCancel(): RedirectResponse
    {
        return redirect()
            ->route('checkout.show')
            ->with('status', 'No payment was taken — your bag is still here when you are ready.');
    }

    protected function redirectToStripe(Order $order): RedirectResponse
    {
        try {
            $checkout = $this->stripe->forOrder($order);
        } catch (ApiErrorException $exception) {
            return $this->failOrder($order, $exception, 'Stripe');
        }

        $order->update([
            'stripe_checkout_session_id' => $checkout->asStripeCheckoutSession()->id,
        ]);

        return $checkout->redirect();
    }

    protected function redirectToPayPal(Order $order): RedirectResponse
    {
        try {
            $approvalUrl = $this->paypal->approvalUrlFor($order);
        } catch (\Throwable $exception) {
            return $this->failOrder($order, $exception, 'PayPal');
        }

        return redirect()->away($approvalUrl);
    }

    protected function failOrder(Order $order, \Throwable $exception, string $provider): RedirectResponse
    {
        Log::error("{$provider} checkout could not be started.", [
            'order_reference' => $order->reference,
            'message' => $exception->getMessage(),
        ]);

        $order->update(['status' => OrderStatus::Failed]);

        return back()->with('error', 'We could not reach our payment provider. Please try again in a moment.');
    }

    /**
     * Label for the region field, matching the default shipping country.
     */
    protected function regionLabel(): string
    {
        $default = array_key_first(config('ballpit.shipping_countries'));

        return config('ballpit.region_labels')[$default] ?? 'State or province';
    }

    protected function stripeConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }

    protected function isEnabled(PaymentProvider $provider): bool
    {
        return match ($provider) {
            PaymentProvider::Stripe => $this->stripeConfigured(),
            PaymentProvider::PayPal => $this->paypal->isConfigured(),
        };
    }

    protected function notConfiguredMessage(PaymentProvider $provider): string
    {
        return match ($provider) {
            PaymentProvider::Stripe => 'Card payments are not configured yet. Add STRIPE_KEY and STRIPE_SECRET to the .env file.',
            PaymentProvider::PayPal => 'PayPal is not configured yet. Add PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET to the .env file.',
        };
    }

    /**
     * Snapshot the cart into a pending order, preserving the prices and
     * colours the customer actually saw.
     */
    protected function createPendingOrder(
        StoreCheckoutRequest $request,
        Cart $cart,
        PaymentProvider $provider,
    ): Order {
        return DB::transaction(function () use ($request, $cart, $provider): Order {
            $order = Order::create([
                ...$request->orderAttributes(),
                'reference' => Order::generateReference(),
                'status' => OrderStatus::Pending,
                'payment_provider' => $provider,
                'subtotal_cents' => $cart->subtotalCents(),
                'shipping_cents' => $cart->shippingCents(),
                'total_cents' => $cart->totalCents(),
                'currency' => config('ballpit.currency'),
            ]);

            foreach ($cart->lines() as $line) {
                $order->items()->create([
                    'ball_type_id' => $line['ballType']->id,
                    'ball_type_name' => $line['ballType']->name,
                    'color_hex' => $line['ballType']->color_hex,
                    'quantity' => $line['quantity'],
                    'unit_price_cents' => $line['ballType']->price_per_ball_cents,
                    'line_total_cents' => $line['line_total_cents'],
                ]);
            }

            return $order->load('items');
        });
    }
}
