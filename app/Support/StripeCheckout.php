<?php

namespace App\Support;

use App\Models\Order;
use Laravel\Cashier\Checkout;

/**
 * Builds the Stripe Checkout session for a pending order.
 *
 * Custom ball mixes have no fixed Stripe Price, so each order line is sent
 * as inline `price_data` rather than a pre-created price ID.
 */
class StripeCheckout
{
    public function forOrder(Order $order): Checkout
    {
        return Checkout::guest()
            ->create($this->lineItems($order), $this->sessionOptions($order));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function lineItems(Order $order): array
    {
        $currency = $order->currency;

        $items = $order->items
            ->map(fn ($item): array => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $item->unit_price_cents,
                    'product_data' => [
                        'name' => $item->ball_type_name,
                        'description' => sprintf(
                            '%s plastic pit balls (%s)',
                            number_format($item->quantity),
                            $item->color_hex,
                        ),
                    ],
                ],
            ])
            ->values()
            ->all();

        if ($order->shipping_cents > 0) {
            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $order->shipping_cents,
                    'product_data' => [
                        'name' => 'Shipping & handling',
                    ],
                ],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionOptions(Order $order): array
    {
        $options = [
            'mode' => 'payment',
            'customer_email' => $order->customer_email,
            'client_reference_id' => $order->reference,
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel'),
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_reference' => $order->reference,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_reference' => $order->reference,
                ],
            ],
        ];

        // We collect the shipping address ourselves, so Stripe is told not to
        // ask again. Passing it along anyway makes it visible on the payment
        // in the Stripe dashboard, which is what fulfilment looks at.
        if ($order->hasShippingAddress()) {
            $options['payment_intent_data']['shipping'] = [
                'name' => $order->shipping_name ?? $order->customer_name,
                'phone' => $order->shipping_phone,
                'address' => array_filter([
                    'line1' => $order->shipping_line1,
                    'line2' => $order->shipping_line2,
                    'city' => $order->shipping_city,
                    'state' => $order->shipping_region,
                    'postal_code' => $order->shipping_postal_code,
                    'country' => $order->shipping_country,
                ], fn (?string $value): bool => filled($value)),
            ];
        }

        /** @var list<string> $paymentMethodTypes */
        $paymentMethodTypes = config('ballpit.payment_method_types', []);

        // When left empty, Stripe automatically offers whichever payment
        // methods are enabled on the account (card, PayPal, wallets, ...).
        if ($paymentMethodTypes !== []) {
            $options['payment_method_types'] = $paymentMethodTypes;
        }

        return $options;
    }
}
