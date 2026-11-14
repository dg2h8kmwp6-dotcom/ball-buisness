<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'ball_type_id',
        'ball_type_name',
        'color_hex',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<BallType, $this>
     */
    public function ballType(): BelongsTo
    {
        return $this->belongsTo(BallType::class);
    }

    public function formattedLineTotal(): string
    {
        return '$'.number_format($this->line_total_cents / 100, 2);
    }
}
