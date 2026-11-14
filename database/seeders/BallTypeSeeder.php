<?php

namespace Database\Seeders;

use App\Models\BallType;
use Illuminate\Database\Seeder;

class BallTypeSeeder extends Seeder
{
    /**
     * The catalogue of ball types available to order.
     *
     * @var list<array<string, mixed>>
     */
    protected array $ballTypes = [
        [
            'name' => 'Classic Red',
            'color_hex' => '#e8392e',
            'price_per_ball_cents' => 12,
            'description' => 'The one everyone pictures. Opaque crush-proof polyethylene.',
        ],
        [
            'name' => 'Classic Blue',
            'color_hex' => '#2b7fd4',
            'price_per_ball_cents' => 12,
            'description' => 'Deep primary blue. Our highest-volume seller.',
        ],
        [
            'name' => 'Classic Yellow',
            'color_hex' => '#f6c62b',
            'price_per_ball_cents' => 12,
            'description' => 'Bright, warm, and impossible to lose in a pit.',
        ],
        [
            'name' => 'Classic Green',
            'color_hex' => '#3faa58',
            'price_per_ball_cents' => 12,
            'description' => 'Grass green. Pairs well with the classic primaries.',
        ],
        [
            'name' => 'Sorbet Orange',
            'color_hex' => '#f5833b',
            'price_per_ball_cents' => 14,
            'description' => 'A softer, pastel-leaning orange for modern play spaces.',
        ],
        [
            'name' => 'Bubblegum Pink',
            'color_hex' => '#f279ae',
            'price_per_ball_cents' => 14,
            'description' => 'Party-ready pink. Very popular for event rentals.',
        ],
        [
            'name' => 'Pearl White',
            'color_hex' => '#f4f1ea',
            'price_per_ball_cents' => 16,
            'description' => 'Subtle pearlescent finish. The photographer favourite.',
        ],
        [
            'name' => 'Smoke Grey',
            'color_hex' => '#9aa3ad',
            'price_per_ball_cents' => 16,
            'description' => 'Muted translucent grey for a calmer, neutral pit.',
        ],
        [
            'name' => 'Deep Purple',
            'color_hex' => '#8257c9',
            'price_per_ball_cents' => 15,
            'description' => 'Rich violet that reads well under coloured lighting.',
        ],
        [
            'name' => 'Mint Teal',
            'color_hex' => '#3fc0b0',
            'price_per_ball_cents' => 15,
            'description' => 'Cool mint with a hint of blue. Great as an accent colour.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->ballTypes as $index => $ballType) {
            BallType::updateOrCreate(
                ['slug' => str($ballType['name'])->slug()->value()],
                [
                    ...$ballType,
                    'slug' => str($ballType['name'])->slug()->value(),
                    'diameter_mm' => 70,
                    'min_quantity' => 100,
                    'quantity_step' => 100,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
