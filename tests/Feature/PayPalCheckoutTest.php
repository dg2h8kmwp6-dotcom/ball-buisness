<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Models\BallType;
use App\Models\Order;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'test-client-id',
            'services.paypal.client_secret' => 'test-client-secret',
            'services.paypal.webhook_id' => 'test-webhook-id',
            // Keep card payments out of the way of the PayPal assertions.
            'cashier.secret' => null,
        ]);

        Cache::forget('paypal.access_token');
    }

    public function test_choosing_paypal_creates_an_order_and_redirects_to_the_approval_url(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response([
                'id' => '5O190127TN364715T',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T'],
                    ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T'],
                ],
            ]),
        ]);

        $red = BallType::factory()->create(['price_per_ball_cents' => 12]);
        $blue = BallType::factory()->create(['price_per_ball_cents' => 10]);

        $response = $this->withSession([Cart::SESSION_KEY => [$red->id => 1000, $blue->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ]);

        $response->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T');

        $order = Order::sole();

        $this->assertSame(PaymentProvider::PayPal, $order->payment_provider);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('5O190127TN364715T', $order->paypal_order_id);
        $this->assertSame(17000 + (int) config('ballpit.shipping_flat_cents'), $order->total_cents);
    }

    public function test_the_create_order_payload_itemises_the_mix_and_adds_up(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        $red = BallType::factory()->create(['name' => 'Classic Red', 'price_per_ball_cents' => 12]);
        $blue = BallType::factory()->create(['name' => 'Classic Blue', 'price_per_ball_cents' => 10]);

        $this->withSession([Cart::SESSION_KEY => [$red->id => 1000, $blue->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ]);

        $order = Order::sole();

        Http::assertSent(function (Request $request) use ($order): bool {
            if (! str_contains($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $body = $request->data();
            $unit = $body['purchase_units'][0];

            // Items carry per-ball prices and whole quantities.
            $items = collect($unit['items']);
            $itemTotal = $items->sum(
                fn (array $item): float => (float) $item['unit_amount']['value'] * (int) $item['quantity']
            );

            return $body['intent'] === 'CAPTURE'
                && $items->count() === 2
                && $unit['custom_id'] === (string) $order->id
                && $unit['invoice_id'] === $order->reference
                // 1,000 x $0.12 + 500 x $0.10 = $170.00
                && abs($itemTotal - 170.00) < 0.001
                && $unit['amount']['breakdown']['item_total']['value'] === '170.00'
                && $unit['amount']['breakdown']['shipping']['value'] === '25.00'
                && $unit['amount']['value'] === '195.00'
                && $body['payment_source']['paypal']['experience_context']['user_action'] === 'PAY_NOW'
                && $body['payment_source']['paypal']['experience_context']['return_url'] === route('checkout.paypal.return');
        });
    }

    public function test_the_create_order_request_is_idempotent_per_order(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ]);

        $reference = Order::sole()->reference;

        Http::assertSent(fn (Request $request): bool => ! str_contains($request->url(), '/v2/checkout/orders')
            || $request->hasHeader('PayPal-Request-Id', 'order-'.$reference));
    }

    public function test_returning_from_paypal_captures_the_payment_and_empties_the_bag(): void
    {
        $order = Order::factory()->create([
            'payment_provider' => PaymentProvider::PayPal,
            'paypal_order_id' => 'PP-RETURN',
        ]);

        $order->items()->create([
            'ball_type_id' => BallType::factory()->create()->id,
            'ball_type_name' => 'Classic Red',
            'color_hex' => '#e8392e',
            'quantity' => 1000,
            'unit_price_cents' => 12,
            'line_total_cents' => 12000,
        ]);

        $this->fakePayPal([
            '*/v2/checkout/orders/PP-RETURN/capture' => Http::response([
                'id' => 'PP-RETURN',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['payments' => ['captures' => [['id' => 'CAP-123', 'status' => 'COMPLETED']]]],
                ],
            ]),
        ]);

        $response = $this->withSession([Cart::SESSION_KEY => [1 => 500]])
            ->get(route('checkout.paypal.return', ['token' => 'PP-RETURN']));

        $response->assertOk();
        $response->assertSee($order->reference);
        $response->assertSessionMissing(Cart::SESSION_KEY);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAP-123', $order->paypal_capture_id);
        $this->assertNotNull($order->paid_at);
    }

    public function test_an_unknown_paypal_token_sends_the_customer_home(): void
    {
        $this->fakePayPal();

        $this->get(route('checkout.paypal.return', ['token' => 'PP-NOPE']))
            ->assertRedirect(route('home'));
    }

    public function test_a_failed_capture_still_shows_the_order_rather_than_erroring(): void
    {
        $order = Order::factory()->create([
            'payment_provider' => PaymentProvider::PayPal,
            'paypal_order_id' => 'PP-FAIL',
        ]);

        $this->fakePayPal([
            '*/v2/checkout/orders/PP-FAIL/capture' => Http::response(['message' => 'boom'], 422),
        ]);

        $response = $this->get(route('checkout.paypal.return', ['token' => 'PP-FAIL']));

        $response->assertOk();
        // Not paid, and the page says so rather than claiming success.
        $response->assertSee('confirming your payment');
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_capturing_an_already_paid_order_does_not_call_paypal_again(): void
    {
        $order = Order::factory()->paid()->create([
            'payment_provider' => PaymentProvider::PayPal,
            'paypal_order_id' => 'PP-DONE',
        ]);

        $this->fakePayPal();

        $this->get(route('checkout.paypal.return', ['token' => 'PP-DONE']))->assertOk();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/capture'));
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_cancelling_at_paypal_returns_to_checkout_with_the_bag_intact(): void
    {
        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->get(route('checkout.paypal.cancel'))
            ->assertRedirect(route('checkout.show'))
            ->assertSessionHas(Cart::SESSION_KEY, [$ballType->id => 500]);
    }

    public function test_paypal_is_refused_when_it_is_not_configured(): void
    {
        config(['services.paypal.client_id' => null, 'services.paypal.client_secret' => null]);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_a_paypal_outage_marks_the_order_failed_and_explains_itself(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response(['message' => 'unavailable'], 503),
        ]);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Failed, Order::sole()->status);
    }

    public function test_the_access_token_is_cached_between_calls(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        $ballType = BallType::factory()->create();

        foreach (range(1, 2) as $ignored) {
            $this->withSession([Cart::SESSION_KEY => [$ballType->id => 500]])
                ->post(route('checkout.store'), [
                    ...$this->validCheckout(),
                    'provider' => 'paypal',
                ]);
        }

        $tokenCalls = collect(Http::recorded())
            ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/v1/oauth2/token'))
            ->count();

        $this->assertSame(1, $tokenCalls);
    }

    public function test_the_collected_address_is_sent_to_paypal_and_locked(): void
    {
        $this->fakePayPal([
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-SHIP',
                'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        $ballType = BallType::factory()->create();

        $this->withSession([Cart::SESSION_KEY => [$ballType->id => 20]])
            ->post(route('checkout.store'), [
                ...$this->validCheckout(),
                'provider' => 'paypal',
            ]);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $body = $request->data();
            $shipping = $body['purchase_units'][0]['shipping'] ?? null;

            return $shipping !== null
                && $shipping['name']['full_name'] === 'Grace Hopper'
                && $shipping['address']['address_line_1'] === '1 Ball Pit Way'
                // admin_area_2 is the city, admin_area_1 the state.
                && $shipping['address']['admin_area_2'] === 'Austin'
                && $shipping['address']['admin_area_1'] === 'TX'
                && $shipping['address']['postal_code'] === '78701'
                && $shipping['address']['country_code'] === 'US'
                // Having supplied one, PayPal must not offer another.
                && $body['payment_source']['paypal']['experience_context']['shipping_preference'] === 'SET_PROVIDED_ADDRESS';
        });
    }

    /**
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
     * @param  array<string, mixed>  $responses
     */
    protected function fakePayPal(array $responses = []): void
    {
        Http::preventStrayRequests();

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'fake-access-token',
                'expires_in' => 3600,
            ]),
            ...$responses,
            // Anything else PayPal-ish fails loudly rather than silently.
            '*' => Http::response(['message' => 'unexpected call'], 418),
        ]);
    }
}
