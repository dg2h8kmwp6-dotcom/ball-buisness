<?php

namespace Database\Seeders;

use App\Models\BallType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The default ball catalogue.
 *
 * These are starting defaults, not the source of truth: the admin area owns
 * the catalogue once it is live. Re-running this seeder therefore only adds
 * ball types that are missing and never overwrites an existing row, so edited
 * prices, descriptions, and uploaded photos survive a redeploy.
 *
 * Use `php artisan ballpit:sync-ball-types --overwrite` to deliberately reset
 * rows back to these defaults.
 */
class BallTypeSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public static array $defaults = [
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
            'name' => 'Lime Punch',
            'color_hex' => '#a8d84a',
            'price_per_ball_cents' => 14,
            'description' => 'High-visibility lime. Reads almost neon in daylight.',
        ],
        [
            'name' => 'Sky Blue',
            'color_hex' => '#7cc7ef',
            'price_per_ball_cents' => 14,
            'description' => 'Pale sky blue. The go-to for soft, calming palettes.',
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

    /**
     * The defaults, keyed by slug and with their derived columns filled in.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (static::$defaults as $index => $ballType) {
            $slug = Str::slug($ballType['name']);

            $defaults[$slug] = [
                ...$ballType,
                'slug' => $slug,
                'diameter_mm' => 70,
                'min_quantity' => 100,
                'quantity_step' => 100,
                'sort_order' => $index,
                'is_active' => true,
            ];
        }

        return $defaults;
    }

    public function run(): void
    {
        $created = 0;

        foreach (static::defaults() as $slug => $attributes) {
            // Only fill gaps — never overwrite a row the admin may have edited.
            if (BallType::where('slug', $slug)->exists()) {
                continue;
            }

            BallType::create($attributes);
            $created += 1;
        }

        $this->command?->info(sprintf(
            'Ball types: %d created, %d already present.',
            $created,
            count(static::defaults()) - $created,
        ));
    }
}
