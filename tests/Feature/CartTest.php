<?php

namespace Tests\Feature;

use App\Models\BallType;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_cart_is_empty(): void
    {
        $cart = app(Cart::class);

        $this->assertTrue($cart->isEmpty());
        $this->assertSame([], $cart->quantities());
        $this->assertSame(0, $cart->totalBalls());
        $this->assertSame(0, $cart->subtotalCents());
        $this->assertSame(0, $cart->shippingCents());
        $this->assertSame(0, $cart->totalCents());
    }

    public function test_replacing_the_contents_drops_zero_and_negative_quantities(): void
    {
        $cart = app(Cart::class);

        $cart->replace([1 => 500, 2 => 0, 3 => -10, '4' => '250']);

        $this->assertSame([1 => 500, 4 => 250], $cart->quantities());
    }

    public function test_totals_are_derived_from_the_current_ball_type_prices(): void
    {
        $red = BallType::factory()->create(['price_per_ball_cents' => 12, 'sort_order' => 1]);
        $blue = BallType::factory()->create(['price_per_ball_cents' => 10, 'sort_order' => 2]);

        $cart = app(Cart::class);
        $cart->replace([$red->id => 1000, $blue->id => 500]);

        $this->assertSame(1500, $cart->totalBalls());
        // 1,000 × 12c + 500 × 10c
        $this->assertSame(17000, $cart->subtotalCents());
        $this->assertSame((int) config('ballpit.shipping_flat_cents'), $cart->shippingCents());
        $this->assertSame(17000 + (int) config('ballpit.shipping_flat_cents'), $cart->totalCents());
    }

    public function test_lines_expose_the_ball_type_quantity_and_line_total(): void
    {
        $ballType = BallType::factory()->create(['price_per_ball_cents' => 15]);

        $cart = app(Cart::class);
        $cart->replace([$ballType->id => 200]);

        $line = $cart->lines()->sole();

        $this->assertTrue($ballType->is($line['ballType']));
        $this->assertSame(200, $line['quantity']);
        $this->assertSame(3000, $line['line_total_cents']);
    }

    public function test_a_ball_type_deleted_after_being_added_is_ignored(): void
    {
        $ballType = BallType::factory()->create(['price_per_ball_cents' => 12]);

        $cart = app(Cart::class);
        $cart->replace([$ballType->id => 500]);

        $ballType->delete();

        $this->assertCount(0, $cart->lines());
        $this->assertSame(0, $cart->subtotalCents());
    }

    public function test_clearing_empties_the_cart(): void
    {
        $ballType = BallType::factory()->create();

        $cart = app(Cart::class);
        $cart->replace([$ballType->id => 500]);
        $cart->clear();

        $this->assertTrue($cart->isEmpty());
    }
}
