<?php

namespace App\Models;

use Database\Factories\BallTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BallType extends Model
{
    /** @use HasFactory<BallTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color_hex',
        'diameter_mm',
        'price_per_ball_cents',
        'min_quantity',
        'quantity_step',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'diameter_mm' => 'integer',
            'price_per_ball_cents' => 'integer',
            'min_quantity' => 'integer',
            'quantity_step' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param  Builder<BallType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<BallType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Price for a single ball, formatted for display.
     */
    public function formattedUnitPrice(): string
    {
        return '$'.number_format($this->price_per_ball_cents / 100, 2);
    }
}
