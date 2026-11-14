<?php

namespace Tests\Feature;

use App\Models\BallType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_headline_figures_are_derived_from_the_catalogue(): void
    {
        BallType::factory()->count(3)->create([
            'min_quantity' => 10,
            'diameter_mm' => 70,
        ]);
        BallType::factory()->inactive()->create(['min_quantity' => 1]);

        $response = $this->get(route('home'));

        $response->assertOk();
        // Three active colours, not four: the hidden one does not count.
        $response->assertSeeInOrder(['Colours', '3']);
        $response->assertSeeInOrder(['Min order', '10']);
        $response->assertSeeInOrder(['Ball size', '70']);
    }

    public function test_the_headline_figures_are_hidden_when_there_is_no_catalogue(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Min order');
    }

    public function test_the_landing_page_shows_the_brand_and_an_order_now_call_to_action(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(config('ballpit.brand_name'));
        $response->assertSee('Order now');
        $response->assertSee(route('order.create'));
    }
}
