<?php

namespace Database\Factories;

use App\Models\BallType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BallType>
 */
class BallTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->colorName().' Ball';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'color_hex' => fake()->hexColor(),
            'diameter_mm' => fake()->randomElement([60, 70, 80]),
            'price_per_ball_cents' => fake()->numberBetween(8, 40),
            'min_quantity' => 10,
            'quantity_step' => 5,
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
