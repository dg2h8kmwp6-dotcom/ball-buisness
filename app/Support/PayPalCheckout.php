<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drives a PayPal order through approval and capture.
 *
 * Mirrors StripeCheckout: the customer is redirected out to approve, comes
 * back to a return URL, and the webhook is the real source of truth for
 * whether the money arrived.
 */
class PayPalCheckout
{
    public function __construct(protected PayPalClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Create the PayPal order and return the URL to send the customer to.
     *
     * PayPal calls this redirect the "payer action" link. Passing the payment
     * source up front means no separate confirm call is needed.
     */
    public function approvalUrlFor(Order $order): string
    {
        $response = $this->client->createOrder(
            $this->payload($order),
            // Keyed to the order so a double submit cannot create two orders.
            'order-'.$order->reference,
        );

        $paypalOrderId = $response['id'] ?? null;

        if (! is_string($paypalOrderId)) {
            throw new RuntimeException('PayPal did not return an order id.');
        }

        $order->update(['paypal_order_id' => $paypalOrderId]);

        return $this->approvalLink($response);
    }

    /**
     * Capture an approved order and mark it paid.
     *
     * Safe to call more than once: an already-captured order is treated as a
     * success so a refreshed return page does not look like a failure.
     */
    public function capture(Order $order): bool
    {
        if ($order->status === OrderStatus::Paid) {
            return true;
        }

        if (blank($order->paypal_order_id)) {
            return false;
        }

        try {
            $response = $this->client->captureOrder(
                $order->paypal_order_id,
                'capture-'.$order->reference,
            );
        } catch (\Throwable $exception) {
            Log::warning('PayPal capture failed.', [
                'order_reference' => $order->reference,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return $this->recordCapture($order, $response);
    }

    /**
     * Mark the order paid from a capture (or order) payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordCapture(Order $order, array $payload): bool
    {
        if (($payload['status'] ?? null) !== 'COMPLETED') {
            return false;
        }

        $capture = $payload['purchase_units'][0]['payments']['captures'][0] ?? null;
        $captureId = is_array($capture) ? ($capture['id'] ?? null) : null;

        if (is_string($captureId)) {
            $order->update(['paypal_capture_id' => $captureId]);
        }

        $order->markPaid();

        return true;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function approvalLink(array $response): string
    {
        $links = $response['links'] ?? [];

        // "payer-action" is returned when the payment source is supplied at
        // creation; "approve" is the classic flow. Accept either.
        foreach (['payer-action', 'approve'] as $rel) {
            foreach ($links as $link) {
                if (($link['rel'] ?? null) === $rel && is_string($link['href'] ?? null)) {
                    return $link['href'];
                }
            }
        }

        throw new RuntimeException('PayPal did not return an approval link.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Order $order): array
    {
        $currency = strtoupper($order->currency);

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                array_filter([
                    'reference_id' => $order->reference,
                    'custom_id' => (string) $order->id,
                    'invoice_id' => $order->reference,
                    'description' => Str::limit('Custom ball pit mix — '.$order->reference, 127, ''),
                    'items' => $order->items->map(
                        fn (OrderItem $item): array => $this->item($item, $currency)
                    )->all(),
                    'shipping' => $this->shipping($order),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $this->money($order->total_cents),
                        'breakdown' => array_filter([
                            'item_total' => [
                                'currency_code' => $currency,
                                'value' => $this->money($order->subtotal_cents),
                            ],
                            'shipping' => $order->shipping_cents > 0 ? [
                                'currency_code' => $currency,
                                'value' => $this->money($order->shipping_cents),
                            ] : null,
                        ]),
                    ],
                ], fn (mixed $value): bool => $value !== null),
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => Str::limit((string) config('ballpit.brand_name'), 127, ''),
                        'landing_page' => 'LOGIN',
                        // We already collected the address, so PayPal shows
                        // ours rather than letting the buyer pick another.
                        'shipping_preference' => $order->hasShippingAddress()
                            ? 'SET_PROVIDED_ADDRESS'
                            : 'GET_FROM_FILE',
                        // Show "Pay Now" rather than "Continue", since we
                        // capture immediately on return.
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('checkout.paypal.return'),
                        'cancel_url' => route('checkout.paypal.cancel'),
                    ],
                ],
            ],
        ];
    }

    /**
     * The shipping block PayPal shows the buyer, or null when we have no
     * address and PayPal should collect one itself.
     *
     * @return array<string, mixed>|null
     */
    protected function shipping(Order $order): ?array
    {
        if (! $order->hasShippingAddress()) {
            return null;
        }

        return [
            'type' => 'SHIPPING',
            'name' => [
                'full_name' => Str::limit($order->shipping_name ?? (string) $order->customer_name, 300, ''),
            ],
            'address' => array_filter([
                'address_line_1' => $order->shipping_line1,
                'address_line_2' => $order->shipping_line2,
                // PayPal's admin_area_2 is the city, admin_area_1 the state.
                'admin_area_2' => $order->shipping_city,
                'admin_area_1' => $order->shipping_region,
                'postal_code' => $order->shipping_postal_code,
                'country_code' => $order->shipping_country,
            ], fn (?string $value): bool => filled($value)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(OrderItem $item, string $currency): array
    {
        return [
            'name' => Str::limit($item->ball_type_name, 127, ''),
            'description' => Str::limit($item->color_hex.' pit balls', 127, ''),
            'quantity' => (string) $item->quantity,
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => $this->money($item->unit_price_cents),
            ],
            'category' => 'PHYSICAL_GOODS',
        ];
    }

    /**
     * PayPal wants decimal strings, and the breakdown must add up exactly.
     */
    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
