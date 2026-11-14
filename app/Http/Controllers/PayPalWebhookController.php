<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\PayPalCheckout;
use App\Support\PayPalClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives PayPal webhooks.
 *
 * As with Stripe, the webhook — not the browser redirect — is what actually
 * decides an order is paid: the customer may never return from PayPal.
 */
class PayPalWebhookController extends Controller
{
    public function __construct(
        protected PayPalClient $client,
        protected PayPalCheckout $checkout,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $event */
        $event = $request->json()->all();

        if (! $this->client->verifyWebhookSignature($this->transmissionHeaders($request), $event)) {
            Log::warning('Rejected an unverified PayPal webhook.', [
                'event_type' => $event['event_type'] ?? null,
            ]);

            // 403 tells PayPal not to treat this as a delivery success.
            return response()->json(['message' => 'Signature verification failed.'], 403);
        }

        $resource = $event['resource'] ?? [];
        $type = $event['event_type'] ?? null;

        match ($type) {
            'CHECKOUT.ORDER.APPROVED' => $this->handleApproved($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($resource),
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.DECLINED' => $this->handleCaptureFailed($resource),
            default => null,
        };

        return response()->json(['message' => 'ok']);
    }

    /**
     * The buyer approved but we may not have captured yet — for instance if
     * they closed the tab before being redirected back.
     *
     * @param  array<string, mixed>  $resource
     */
    protected function handleApproved(array $resource): void
    {
        $order = $this->resolveOrderFromOrderResource($resource);

        if ($order && $order->status === OrderStatus::Pending) {
            $this->checkout->capture($order);
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function handleCaptureCompleted(array $resource): void
    {
        $order = $this->resolveOrderFromCaptureResource($resource);

        if (! $order || $order->status === OrderStatus::Paid) {
            return;
        }

        $captureId = $resource['id'] ?? null;

        if (is_string($captureId)) {
            $order->update(['paypal_capture_id' => $captureId]);
        }

        $order->markPaid();

        Log::info('Order marked paid from PayPal webhook.', [
            'order_reference' => $order->reference,
        ]);

        // TODO: dispatch the order confirmation mail and notify the warehouse.
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function handleCaptureFailed(array $resource): void
    {
        $order = $this->resolveOrderFromCaptureResource($resource);

        if ($order && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Failed]);
        }
    }

    /**
     * A CHECKOUT.ORDER.* resource is the PayPal order itself.
     *
     * @param  array<string, mixed>  $resource
     */
    protected function resolveOrderFromOrderResource(array $resource): ?Order
    {
        $paypalOrderId = $resource['id'] ?? null;

        if (is_string($paypalOrderId)) {
            $order = Order::where('paypal_order_id', $paypalOrderId)->first();

            if ($order) {
                return $order;
            }
        }

        return $this->resolveByCustomId($resource['purchase_units'][0]['custom_id'] ?? null);
    }

    /**
     * A PAYMENT.CAPTURE.* resource is the capture, which carries our custom_id
     * and links back to the order.
     *
     * @param  array<string, mixed>  $resource
     */
    protected function resolveOrderFromCaptureResource(array $resource): ?Order
    {
        $order = $this->resolveByCustomId($resource['custom_id'] ?? null);

        if ($order) {
            return $order;
        }

        $invoiceId = $resource['invoice_id'] ?? null;

        if (is_string($invoiceId)) {
            return Order::where('reference', $invoiceId)->first();
        }

        return null;
    }

    protected function resolveByCustomId(mixed $customId): ?Order
    {
        if (is_string($customId) || is_int($customId)) {
            return Order::find((int) $customId);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function transmissionHeaders(Request $request): array
    {
        $headers = [];

        foreach ([
            'paypal-auth-algo',
            'paypal-cert-url',
            'paypal-transmission-id',
            'paypal-transmission-sig',
            'paypal-transmission-time',
        ] as $header) {
            $value = $request->header($header);

            if (is_string($value)) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }
}
