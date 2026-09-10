<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rendering-level checks for the authentication UI: the pages come up, they use
 * the shared layouts, and server-side validation messages are shown on the page.
 */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function guestPages(): array
    {
        return [
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
            'reset password' => ['/reset-password/some-token'],
        ];
    }

    #[DataProvider('guestPages')]
    public function test_guest_auth_pages_render(string $uri): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertSee('name="_token"', false)   // CSRF field present
            ->assertSee(config('app.name'));
    }

    public function test_login_shows_validation_errors_on_the_page(): void
    {
        $this->get('/login'); // establish session

        $this->followingRedirects()
            ->post('/login', ['email' => '', 'password' => ''])
            ->assertOk()
            ->assertSee('required'); // validation copy rendered by x-input
    }

    public function test_dashboard_uses_the_application_shell(): void
    {
        $user = User::factory()->create();
        $user->schools()->attach(School::factory()->create());

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('sidebarOpen', false)          // app shell (mobile drawer state)
            ->assertSee($user->name);                  // user menu in header
    }

    public function test_verification_and_confirm_pages_render(): void
    {
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/verify-email')->assertOk();

        $verified = User::factory()->create();
        $this->actingAs($verified)->get('/confirm-password')->assertOk();
    }
}
