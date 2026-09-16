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

    // -- M29.5: school-branded login / register --------------------------

    public function test_login_and_register_show_the_generic_platform_identity_with_no_school_hint(): void
    {
        $this->get('/login')->assertOk()->assertSee(config('app.name'))->assertDontSee('School Portal');
        $this->get('/register')->assertOk()->assertSee(config('app.name'))->assertDontSee('School Portal');
    }

    public function test_login_and_register_show_the_schools_own_identity_with_a_valid_slug_hint(): void
    {
        $school = School::factory()->create(['name' => 'Greenfield Academy']);

        $this->get('/login?school='.$school->slug)
            ->assertOk()
            ->assertSee('Greenfield Academy')
            ->assertSee('Powered by Eden Education Suite');

        $this->get('/register?school='.$school->slug)
            ->assertOk()
            ->assertSee('Greenfield Academy');
    }

    public function test_login_ignores_an_unknown_or_suspended_school_slug(): void
    {
        $this->get('/login?school=not-a-real-school')
            ->assertOk()
            ->assertSee(config('app.name'));

        $suspended = School::factory()->suspended()->create(['name' => 'Closed Academy']);
        $this->get('/login?school='.$suspended->slug)
            ->assertOk()
            ->assertDontSee('Closed Academy');
    }

    public function test_a_school_hinted_registration_still_only_creates_a_bare_account(): void
    {
        // The school hint is cosmetic branding only — registering through it
        // must not join the school or otherwise change what store() does.
        $school = School::factory()->create();

        $this->post('/register?school='.$school->slug, [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'new.person@example.com')->firstOrFail();
        $this->assertFalse($user->schools()->exists(), 'registration must not auto-join any school');
    }
}
