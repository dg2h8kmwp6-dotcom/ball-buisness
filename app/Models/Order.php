<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'status',
        'customer_name',
        'customer_email',
        'subtotal_cents',
        'shipping_cents',
        'total_cents',
        'currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Generate a human-friendly order reference such as "BP-7F3K9Q".
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'BP-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function totalBalls(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function formattedTotal(): string
    {
        return '$'.number_format($this->total_cents / 100, 2);
    }

    public function markPaid(?string $paymentIntentId = null): void
    {
        $this->update([
            'status' => OrderStatus::Paid,
            'stripe_payment_intent_id' => $paymentIntentId ?? $this->stripe_payment_intent_id,
            'paid_at' => now(),
        ]);
    }
}
