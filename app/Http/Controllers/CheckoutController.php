<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreCheckoutRequest;
use App\Models\Order;
use App\Support\Cart;
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
    /**
     * Review the order and collect contact details.
     */
    public function show(Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()
                ->route('order.create')
                ->with('status', 'Your bag is empty — pick some balls to get started.');
        }

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotalCents' => $cart->subtotalCents(),
            'shippingCents' => $cart->shippingCents(),
            'totalCents' => $cart->totalCents(),
            'totalBalls' => $cart->totalBalls(),
            'stripeConfigured' => filled(config('cashier.secret')),
        ]);
    }

    /**
     * Create the pending order and hand the customer off to Stripe Checkout.
     */
    public function store(
        StoreCheckoutRequest $request,
        Cart $cart,
        StripeCheckout $stripeCheckout,
    ): RedirectResponse {
        if ($cart->isEmpty()) {
            return redirect()
                ->route('order.create')
                ->with('status', 'Your bag is empty — pick some balls to get started.');
        }

        if (blank(config('cashier.secret'))) {
            return back()->with('error', 'Payments are not configured yet. Add your STRIPE_KEY and STRIPE_SECRET to the .env file.');
        }

        $order = $this->createPendingOrder($request, $cart);

        try {
            $checkout = $stripeCheckout->forOrder($order);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe Checkout session could not be created.', [
                'order_reference' => $order->reference,
                'message' => $exception->getMessage(),
            ]);

            $order->update(['status' => OrderStatus::Failed]);

            return back()->with('error', 'We could not reach our payment provider. Please try again in a moment.');
        }

        $order->update([
            'stripe_checkout_session_id' => $checkout->asStripeCheckoutSession()->id,
        ]);

        return $checkout->redirect();
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
        if ($order->status !== OrderStatus::Paid) {
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
     * Snapshot the cart into a pending order, preserving the prices and
     * colours the customer actually saw.
     */
    protected function createPendingOrder(StoreCheckoutRequest $request, Cart $cart): Order
    {
        return DB::transaction(function () use ($request, $cart): Order {
            $order = Order::create([
                'reference' => Order::generateReference(),
                'status' => OrderStatus::Pending,
                'customer_name' => $request->validated('customer_name'),
                'customer_email' => $request->validated('customer_email'),
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
