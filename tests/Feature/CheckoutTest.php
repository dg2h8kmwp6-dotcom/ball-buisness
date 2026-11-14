<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\BallType;
use App\Models\Order;
use App\Support\Cart;
use App\Support\StripeCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_checkout_page_redirects_when_the_bag_is_empty(): void
    {
        $response = $this->get(route('checkout.show'));

        $response->assertRedirect(route('order.create'));
        $response->assertSessionHas('status');
    }

    public function test_the_checkout_page_summarises_the_bag(): void
    {
        $ballType = BallType::factory()->create([
            'name' => 'Classic Red',
            'price_per_ball_cents' => 12,
        ]);

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$ballType->id => 1000]])
            ->get(route('checkout.show'));

        $response->assertOk();
        $response->assertSee('Classic Red');
        $response->assertSee('1,000');
        // 1,000 balls at $0.12 each.
        $response->assertSee('$120.00');
    }

    public function test_contact_details_are_required(): void
    {
        $ballType = BallType::factory()->create();

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                'customer_name' => '',
                'customer_email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors(['customer_name', 'customer_email']);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_is_blocked_with_a_clear_message_when_stripe_is_not_configured(): void
    {
        config(['cashier.secret' => null]);

        $ballType = BallType::factory()->create();

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.com',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    public function test_an_order_is_created_and_the_customer_is_redirected_to_stripe(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $red = BallType::factory()->create(['name' => 'Classic Red', 'price_per_ball_cents' => 12]);
        $blue = BallType::factory()->create(['name' => 'Classic Blue', 'price_per_ball_cents' => 10]);

        $this->fakeStripeCheckout('https://checkout.stripe.test/pay/cs_test_123', 'cs_test_123');

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$red->id => 1000, $blue->id => 500]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.com',
            ]);

        $response->assertRedirect('https://checkout.stripe.test/pay/cs_test_123');

        $order = Order::sole();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('ada@example.com', $order->customer_email);
        $this->assertSame('cs_test_123', $order->stripe_checkout_session_id);
        // 1,000 × $0.12 + 500 × $0.10 = $170.00
        $this->assertSame(17000, $order->subtotal_cents);
        $this->assertSame(17000 + (int) config('ballpit.shipping_flat_cents'), $order->total_cents);
        $this->assertCount(2, $order->items);
        $this->assertSame(1500, $order->totalBalls());
    }

    public function test_order_items_snapshot_the_price_and_colour_at_the_time_of_ordering(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $ballType = BallType::factory()->create([
            'name' => 'Classic Red',
            'color_hex' => '#e8392e',
            'price_per_ball_cents' => 12,
        ]);

        $this->fakeStripeCheckout();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 300]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.com',
            ]);

        $ballType->update(['name' => 'Renamed', 'price_per_ball_cents' => 99]);

        $item = Order::sole()->items()->sole();

        $this->assertSame('Classic Red', $item->ball_type_name);
        $this->assertSame('#e8392e', $item->color_hex);
        $this->assertSame(12, $item->unit_price_cents);
        $this->assertSame(3600, $item->line_total_cents);
    }

    public function test_abandoning_payment_returns_the_customer_to_checkout_with_the_bag_intact(): void
    {
        $ballType = BallType::factory()->create();

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->get(route('checkout.cancel'));

        $response->assertRedirect(route('checkout.show'));
        $response->assertSessionHas('status');
        $response->assertSessionHas(Cart::SESSION_KEY, [$ballType->id => 500]);
    }

    public function test_the_success_page_shows_a_paid_order_and_empties_the_bag(): void
    {
        $order = Order::factory()->paid()->create([
            'stripe_checkout_session_id' => 'cs_test_paid',
        ]);

        $order->items()->create([
            'ball_type_id' => BallType::factory()->create()->id,
            'ball_type_name' => 'Classic Red',
            'color_hex' => '#e8392e',
            'quantity' => 1000,
            'unit_price_cents' => 12,
            'line_total_cents' => 12000,
        ]);

        $response = $this
            ->withSession([Cart::SESSION_KEY => [1 => 1000]])
            ->get(route('checkout.success', ['session_id' => 'cs_test_paid']));

        $response->assertOk();
        $response->assertSee($order->reference);
        $response->assertSee('Classic Red');
        $response->assertSessionMissing(Cart::SESSION_KEY);
    }

    public function test_the_success_page_redirects_home_for_an_unknown_session(): void
    {
        $response = $this->get(route('checkout.success', ['session_id' => 'cs_test_unknown']));

        $response->assertRedirect(route('home'));
    }

    /**
     * Swap the Stripe Checkout builder for one that returns a canned session,
     * so the checkout flow can be exercised without touching the Stripe API.
     */
    protected function fakeStripeCheckout(
        string $url = 'https://checkout.stripe.test/pay/cs_test_default',
        string $sessionId = 'cs_test_default',
    ): void {
        $session = Session::constructFrom(['id' => $sessionId, 'url' => $url]);

        $fake = new class($session) extends StripeCheckout
        {
            public function __construct(protected Session $session) {}

            public function forOrder(Order $order): Checkout
            {
                return new Checkout(null, $this->session);
            }
        };

        $this->app->instance(StripeCheckout::class, $fake);
    }
}
