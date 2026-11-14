<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(5000, 200000);
        $shipping = 2500;

        return [
            'reference' => Order::generateReference(),
            'status' => OrderStatus::Pending,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents' => $subtotal + $shipping,
            'currency' => 'usd',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'stripe_payment_intent_id' => 'pi_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'paid_at' => now(),
        ]);
    }
}
