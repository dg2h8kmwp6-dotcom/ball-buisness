<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
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

    public function test_an_empty_bag_gets_its_own_page_pointing_at_the_builder(): void
    {
        $response = $this->get(route('checkout.show'));

        // Deliberately not a redirect to the builder: that made the bag and
        // order pages look like the same page.
        $response->assertOk();
        $response->assertSee('Your bag is empty');
        $response->assertSee(route('order.create'));
        $response->assertDontSee('Choose your balls');
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
                'provider' => 'stripe',
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
                ...$this->validCheckout(),
                'provider' => 'stripe',
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
                ...$this->validCheckout(),
                'provider' => 'stripe',
            ]);

        $response->assertRedirect('https://checkout.stripe.test/pay/cs_test_123');

        $order = Order::sole();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentProvider::Stripe, $order->payment_provider);
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
                ...$this->validCheckout(),
                'provider' => 'stripe',
            ]);

        $ballType->update(['name' => 'Renamed', 'price_per_ball_cents' => 99]);

        $item = Order::sole()->items()->sole();

        $this->assertSame('Classic Red', $item->ball_type_name);
        $this->assertSame('#e8392e', $item->color_hex);
        $this->assertSame(12, $item->unit_price_cents);
        $this->assertSame(3600, $item->line_total_cents);
    }

    public function test_a_payment_method_must_be_chosen(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
            ])
            ->assertSessionHasErrors('provider');

        $this->assertSame(0, Order::count());
    }

    public function test_an_unknown_payment_method_is_rejected(): void
    {
        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'bitcoin',
            ])
            ->assertSessionHasErrors('provider');

        $this->assertSame(0, Order::count());
    }

    public function test_the_checkout_page_only_offers_providers_that_are_configured(): void
    {
        config([
            'cashier.secret' => 'sk_test_fake',
            'services.paypal.client_id' => null,
            'services.paypal.client_secret' => null,
        ]);

        $ballType = BallType::factory()->create();

        $response = $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->get(route('checkout.show'));

        $response->assertOk();
        $response->assertSee('Pay by card');
        $response->assertDontSee('value="paypal"', escape: false);
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

    public function test_the_shipping_address_is_required(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.com',
                'provider' => 'stripe',
            ])
            ->assertSessionHasErrors([
                'shipping_name',
                'shipping_line1',
                'shipping_city',
                'shipping_region',
                'shipping_postal_code',
                'shipping_country',
            ]);

        $this->assertSame(0, Order::count());
    }

    public function test_the_shipping_address_is_stored_on_the_order(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $ballType = BallType::factory()->create();
        $this->fakeStripeCheckout();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 20]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'stripe',
            ]);

        $order = Order::sole();

        $this->assertSame('Grace Hopper', $order->shipping_name);
        $this->assertSame('1 Ball Pit Way', $order->shipping_line1);
        $this->assertSame('Unit 4', $order->shipping_line2);
        $this->assertSame('Austin', $order->shipping_city);
        $this->assertSame('TX', $order->shipping_region);
        $this->assertSame('78701', $order->shipping_postal_code);
        $this->assertSame('US', $order->shipping_country);
        $this->assertSame('555-0100', $order->shipping_phone);
        $this->assertSame('Leave at the loading dock.', $order->delivery_notes);
        $this->assertTrue($order->hasShippingAddress());
    }

    public function test_an_unsupported_shipping_country_is_refused(): void
    {
        config([
            'cashier.secret' => 'sk_test_fake',
            'ballpit.shipping_countries' => ['US' => 'United States'],
        ]);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 20]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'shipping_country' => 'FR',
                'provider' => 'stripe',
            ])
            ->assertSessionHasErrors('shipping_country');

        $this->assertSame(0, Order::count());
    }

    public function test_the_collected_address_is_passed_to_stripe_so_it_is_not_asked_twice(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);

        $order = Order::factory()->create([
            'shipping_name' => 'Grace Hopper',
            'shipping_line1' => '1 Ball Pit Way',
            'shipping_city' => 'Austin',
            'shipping_region' => 'TX',
            'shipping_postal_code' => '78701',
            'shipping_country' => 'US',
        ]);

        $options = (new class extends StripeCheckout
        {
            /** @return array<string, mixed> */
            public function optionsFor(Order $order): array
            {
                return $this->sessionOptions($order);
            }
        })->optionsFor($order);

        $shipping = $options['payment_intent_data']['shipping'];

        $this->assertSame('Grace Hopper', $shipping['name']);
        $this->assertSame('1 Ball Pit Way', $shipping['address']['line1']);
        $this->assertSame('Austin', $shipping['address']['city']);
        $this->assertSame('TX', $shipping['address']['state']);
        $this->assertSame('US', $shipping['address']['country']);
        // We collect it ourselves, so Stripe must not ask again.
        $this->assertArrayNotHasKey('shipping_address_collection', $options);
    }

    /**
     * A complete set of contact and shipping details.
     *
     * @return array<string, string>
     */
    protected function validCheckout(): array
    {
        return [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'shipping_name' => 'Grace Hopper',
            'shipping_line1' => '1 Ball Pit Way',
            'shipping_line2' => 'Unit 4',
            'shipping_city' => 'Austin',
            'shipping_region' => 'TX',
            'shipping_postal_code' => '78701',
            'shipping_country' => 'US',
            'shipping_phone' => '555-0100',
            'delivery_notes' => 'Leave at the loading dock.',
        ];
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
