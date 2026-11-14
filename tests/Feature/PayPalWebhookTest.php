<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
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
        ]);

        Cache::forget('paypal.access_token');
    }

    public function test_a_completed_capture_marks_the_order_paid(): void
    {
        $this->fakeVerification(verified: true);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-999',
                'status' => 'COMPLETED',
                'custom_id' => (string) $order->id,
                'invoice_id' => $order->reference,
            ],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAP-999', $order->paypal_capture_id);
        $this->assertNotNull($order->paid_at);
    }

    public function test_an_unverified_webhook_is_rejected_and_changes_nothing(): void
    {
        $this->fakeVerification(verified: false);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-FORGED',
                'status' => 'COMPLETED',
                'custom_id' => (string) $order->id,
            ],
        ])->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_a_webhook_without_signature_headers_is_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600])]);

        $order = $this->pendingOrder();

        // No PAYPAL-* headers at all.
        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'status' => 'COMPLETED', 'custom_id' => (string) $order->id],
        ])->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_the_webhook_is_rejected_when_no_webhook_id_is_configured(): void
    {
        config(['services.paypal.webhook_id' => null]);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'status' => 'COMPLETED', 'custom_id' => (string) $order->id],
        ])->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_the_order_is_found_by_invoice_id_when_custom_id_is_missing(): void
    {
        $this->fakeVerification(verified: true);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-INV',
                'status' => 'COMPLETED',
                'invoice_id' => $order->reference,
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_a_replayed_capture_webhook_leaves_the_paid_timestamp_alone(): void
    {
        $this->fakeVerification(verified: true);

        $order = Order::factory()->paid()->create([
            'payment_provider' => PaymentProvider::PayPal,
            'paypal_order_id' => 'PP-1',
            'paid_at' => now()->subDay(),
        ]);

        $paidAt = $order->paid_at;

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-2', 'status' => 'COMPLETED', 'custom_id' => (string) $order->id],
        ])->assertOk();

        $this->assertTrue($paidAt->equalTo($order->refresh()->paid_at));
    }

    public function test_a_denied_capture_fails_the_order(): void
    {
        $this->fakeVerification(verified: true);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => ['id' => 'CAP-DENIED', 'custom_id' => (string) $order->id],
        ])->assertOk();

        $this->assertSame(OrderStatus::Failed, $order->refresh()->status);
    }

    public function test_an_approved_order_webhook_captures_a_payment_we_missed(): void
    {
        $order = $this->pendingOrder();

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/PP-APPROVED/capture' => Http::response([
                'id' => 'PP-APPROVED',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['payments' => ['captures' => [['id' => 'CAP-LATE']]]],
                ],
            ]),
        ]);

        $this->postWebhook([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'PP-APPROVED',
                'purchase_units' => [['custom_id' => (string) $order->id]],
            ],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAP-LATE', $order->paypal_capture_id);
    }

    public function test_unrelated_events_are_acknowledged_without_changes(): void
    {
        $this->fakeVerification(verified: true);

        $order = $this->pendingOrder();

        $this->postWebhook([
            'event_type' => 'BILLING.SUBSCRIPTION.CREATED',
            'resource' => ['id' => 'SUB-1'],
        ])->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_the_webhook_route_is_exempt_from_csrf(): void
    {
        $this->fakeVerification(verified: true);

        $order = $this->pendingOrder();

        // A form POST with no CSRF token would otherwise be a 419.
        $this->post(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-CSRF', 'status' => 'COMPLETED', 'custom_id' => (string) $order->id],
        ], $this->signatureHeaders())->assertOk();
    }

    protected function pendingOrder(): Order
    {
        return Order::factory()->create([
            'payment_provider' => PaymentProvider::PayPal,
            'paypal_order_id' => 'PP-APPROVED',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postWebhook(array $payload): TestResponse
    {
        return $this->postJson(route('paypal.webhook'), $payload, $this->signatureHeaders());
    }

    /**
     * @return array<string, string>
     */
    protected function signatureHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/cert.pem',
            'PAYPAL-TRANSMISSION-ID' => 'txn-1',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
            'PAYPAL-TRANSMISSION-TIME' => '2026-11-14T17:00:00Z',
        ];
    }

    protected function fakeVerification(bool $verified): void
    {
        Http::preventStrayRequests();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => $verified ? 'SUCCESS' : 'FAILURE',
            ]),
        ]);
    }
}
