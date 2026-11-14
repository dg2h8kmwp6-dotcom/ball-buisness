<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-login:127.0.0.1');
    }

    public function test_the_admin_area_redirects_guests_to_the_login_page(): void
    {
        $this->get(route('admin.ball-types.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_signed_in_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.ball-types.index'))
            ->assertForbidden();
    }

    public function test_an_admin_can_reach_the_catalogue(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.ball-types.index'))
            ->assertOk()
            ->assertSee('Ball types');
    }

    public function test_an_admin_can_sign_in(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('admin.ball-types.index'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_non_admin_cannot_sign_in_to_the_admin_area(): void
    {
        User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => 'secret-password',
            'is_admin' => false,
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => 'shopper@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_bad_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_admin' => true,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // Even the correct password is refused once the limiter trips.
        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_admin_can_sign_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_the_make_admin_command_grants_access_to_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'is_admin' => false]);

        $this->artisan('ballpit:make-admin', ['email' => 'owner@example.com'])
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_the_make_admin_command_creates_a_missing_account(): void
    {
        $this->artisan('ballpit:make-admin', [
            'email' => 'new@example.com',
            '--password' => 'chosen-password',
        ])->assertSuccessful();

        $user = User::where('email', 'new@example.com')->sole();

        $this->assertTrue($user->is_admin);
    }
}
