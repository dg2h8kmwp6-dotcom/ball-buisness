<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_shows_the_brand_and_an_order_now_call_to_action(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(config('ballpit.brand_name'));
        $response->assertSee('Order now');
        $response->assertSee(route('order.create'));
    }
}
