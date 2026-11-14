<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Listeners\FulfillOrderOnPayment;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_checkout_session_marks_the_order_paid(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_1']);

        $this->handleWebhook('checkout.session.completed', [
            'id' => 'cs_test_1',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_1',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('pi_test_1', $order->stripe_payment_intent_id);
        $this->assertNotNull($order->paid_at);
    }

    public function test_an_unpaid_checkout_session_leaves_the_order_pending(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_2']);

        $this->handleWebhook('checkout.session.completed', [
            'id' => 'cs_test_2',
            'payment_status' => 'unpaid',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_the_order_is_resolved_by_session_id_when_metadata_is_missing(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_3']);

        $this->handleWebhook('checkout.session.completed', [
            'id' => 'cs_test_3',
            'payment_status' => 'paid',
        ]);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_a_replayed_webhook_does_not_move_the_paid_timestamp(): void
    {
        $order = Order::factory()->paid()->create([
            'stripe_checkout_session_id' => 'cs_test_4',
            'paid_at' => now()->subDay(),
        ]);

        $paidAt = $order->paid_at;

        $this->handleWebhook('checkout.session.completed', [
            'id' => 'cs_test_4',
            'payment_status' => 'paid',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertTrue($paidAt->equalTo($order->refresh()->paid_at));
    }

    public function test_an_expired_checkout_session_cancels_a_pending_order(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_5']);

        $this->handleWebhook('checkout.session.expired', [
            'id' => 'cs_test_5',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_a_failed_async_payment_fails_the_order(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_6']);

        $this->handleWebhook('checkout.session.async_payment_failed', [
            'id' => 'cs_test_6',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(OrderStatus::Failed, $order->refresh()->status);
    }

    public function test_unrelated_webhook_events_are_ignored(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_7']);

        $this->handleWebhook('customer.subscription.updated', ['id' => 'sub_test_1']);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_the_listener_is_wired_up_to_cashier_webhook_events(): void
    {
        $order = Order::factory()->create(['stripe_checkout_session_id' => 'cs_test_8']);

        // Dispatch through the real dispatcher to prove auto-discovery found
        // the listener, not just that the handler works when called directly.
        WebhookReceived::dispatch([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_8',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_8',
            ]],
        ]);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $sessionObject
     */
    protected function handleWebhook(string $type, array $sessionObject): void
    {
        app(FulfillOrderOnPayment::class)->handle(new WebhookReceived([
            'type' => $type,
            'data' => ['object' => $sessionObject],
        ]));
    }
}
