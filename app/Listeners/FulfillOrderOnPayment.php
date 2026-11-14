<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Marks orders paid (or failed) from Stripe webhooks.
 *
 * Stripe is the source of truth for payment state: the customer's browser
 * may never make it back to the success URL, so fulfilment hangs off the
 * webhook rather than the redirect.
 */
class FulfillOrderOnPayment
{
    public function handle(WebhookReceived $event): void
    {
        // Cashier only verifies signatures when a webhook secret is set, so
        // without one this endpoint would accept a forged "payment
        // succeeded" and mark an order paid. Refuse rather than trust it.
        if (blank(config('cashier.webhook.secret'))) {
            Log::warning('Ignored a Stripe webhook because STRIPE_WEBHOOK_SECRET is not set, so its signature could not be verified.', [
                'event_type' => $event->payload['type'] ?? null,
            ]);

            return;
        }

        match ($event->payload['type'] ?? null) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->payload),
            'checkout.session.async_payment_succeeded' => $this->handleCheckoutCompleted($event->payload),
            'checkout.session.async_payment_failed' => $this->handleCheckoutFailed($event->payload),
            'checkout.session.expired' => $this->handleCheckoutExpired($event->payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? [];

        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $order = $this->resolveOrder($session);

        if (! $order || $order->status === OrderStatus::Paid) {
            return;
        }

        $paymentIntent = $session['payment_intent'] ?? null;

        $order->markPaid(is_string($paymentIntent) ? $paymentIntent : null);

        Log::info('Order marked paid from Stripe webhook.', [
            'order_reference' => $order->reference,
        ]);

        // TODO: dispatch the order confirmation mail and notify the warehouse.
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutFailed(array $payload): void
    {
        $order = $this->resolveOrder($payload['data']['object'] ?? []);

        if ($order && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Failed]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutExpired(array $payload): void
    {
        $order = $this->resolveOrder($payload['data']['object'] ?? []);

        if ($order && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Cancelled]);
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    protected function resolveOrder(array $session): ?Order
    {
        $orderId = $session['metadata']['order_id'] ?? null;

        if (is_string($orderId) || is_int($orderId)) {
            $order = Order::find((int) $orderId);

            if ($order) {
                return $order;
            }
        }

        $sessionId = $session['id'] ?? null;

        if (is_string($sessionId)) {
            return Order::where('stripe_checkout_session_id', $sessionId)->first();
        }

        return null;
    }
}
