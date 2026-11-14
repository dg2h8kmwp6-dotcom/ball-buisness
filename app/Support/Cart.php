<?php

namespace App\Support;

use App\Models\BallType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * The guest shopping cart, persisted in the session as a map of
 * ball type ID => quantity of individual balls.
 */
class Cart
{
    public const SESSION_KEY = 'ball_cart';

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        /** @var array<int, int> $quantities */
        $quantities = Session::get(self::SESSION_KEY, []);

        return $quantities;
    }

    /**
     * Replace the cart contents with the given quantities, dropping any
     * ball type that was set to zero.
     *
     * @param  array<int|string, int|string>  $quantities
     */
    public function replace(array $quantities): void
    {
        $clean = [];

        foreach ($quantities as $ballTypeId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity > 0) {
                $clean[(int) $ballTypeId] = $quantity;
            }
        }

        Session::put(self::SESSION_KEY, $clean);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return $this->quantities() === [];
    }

    /**
     * The cart contents resolved against the ball types table.
     *
     * @return Collection<int, array{ballType: BallType, quantity: int, line_total_cents: int}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return collect();
        }

        return BallType::query()
            ->whereIn('id', array_keys($quantities))
            ->ordered()
            ->get()
            ->map(fn (BallType $ballType): array => [
                'ballType' => $ballType,
                'quantity' => $quantities[$ballType->id],
                'line_total_cents' => $ballType->price_per_ball_cents * $quantities[$ballType->id],
            ])
            ->values();
    }

    public function subtotalCents(): int
    {
        return (int) $this->lines()->sum('line_total_cents');
    }

    public function shippingCents(): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return (int) config('ballpit.shipping_flat_cents', 0);
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() + $this->shippingCents();
    }

    public function totalBalls(): int
    {
        return array_sum($this->quantities());
    }
}
