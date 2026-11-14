<?php

namespace Tests\Feature;

use App\Models\BallType;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_order_page_lists_the_active_ball_types(): void
    {
        $active = BallType::factory()->create(['name' => 'Classic Red', 'sort_order' => 1]);
        $inactive = BallType::factory()->inactive()->create(['name' => 'Retired Beige']);

        $response = $this->get(route('order.create'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee($inactive->name);
        $response->assertSee('quantities['.$active->id.']', escape: false);
    }

    public function test_the_order_page_prefills_quantities_already_in_the_bag(): void
    {
        $ballType = BallType::factory()->create();

        $response = $this
            ->withSession([Cart::SESSION_KEY => [$ballType->id => 2500]])
            ->get(route('order.create'));

        $response->assertOk();
        $response->assertSee('value="2500"', escape: false);
    }

    public function test_a_selection_is_stored_in_the_bag_and_sends_the_customer_to_checkout(): void
    {
        $red = BallType::factory()->create();
        $blue = BallType::factory()->create();

        $response = $this->post(route('order.store'), [
            'quantities' => [
                $red->id => 1000,
                $blue->id => 500,
            ],
        ]);

        $response->assertRedirect(route('checkout.show'));
        $response->assertSessionHas(Cart::SESSION_KEY, [
            $red->id => 1000,
            $blue->id => 500,
        ]);
    }

    public function test_ball_types_left_at_zero_are_dropped_from_the_selection(): void
    {
        $red = BallType::factory()->create();
        $blue = BallType::factory()->create();

        $response = $this->post(route('order.store'), [
            'quantities' => [
                $red->id => 300,
                $blue->id => 0,
            ],
        ]);

        $response->assertRedirect(route('checkout.show'));
        $response->assertSessionHas(Cart::SESSION_KEY, [$red->id => 300]);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $ballType = BallType::factory()->create();

        $response = $this->post(route('order.store'), [
            'quantities' => [$ballType->id => 0],
        ]);

        $response->assertSessionHasErrors('quantities');
        $response->assertSessionMissing(Cart::SESSION_KEY);
    }

    public function test_an_inactive_ball_type_cannot_be_ordered(): void
    {
        $ballType = BallType::factory()->inactive()->create();

        $response = $this->post(route('order.store'), [
            'quantities' => [$ballType->id => 500],
        ]);

        $response->assertSessionHasErrors('quantities');
    }

    public function test_a_quantity_beyond_the_online_maximum_is_rejected(): void
    {
        $ballType = BallType::factory()->create();

        $response = $this->post(route('order.store'), [
            'quantities' => [$ballType->id => config('ballpit.max_quantity_per_type') + 1],
        ]);

        $response->assertSessionHasErrors('quantities.'.$ballType->id);
    }
}
