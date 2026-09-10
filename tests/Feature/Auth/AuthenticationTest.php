<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->withoutVite()->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_error_message_does_not_reveal_whether_the_email_exists(): void
    {
        User::factory()->create(['email' => 'real@example.com']);

        // Both a real address with the wrong password and an unknown address
        // return the identical generic message.
        $this->from('/login')->post('/login', [
            'email' => 'real@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->flushSession();

        $this->from('/login')->post('/login', [
            'email' => 'ghost@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $idBefore = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotSame($idBefore, session()->getId());
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        Event::fake([Lockout::class]);
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        Event::assertDispatched(Lockout::class);

        // Even the correct password is refused while the lockout is in effect.
        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_protected_routes_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/settings/profile')->assertRedirect('/login');
    }

    public function test_authenticated_verified_user_can_reach_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withoutVite()->get('/dashboard')->assertOk()->assertSee('Dashboard');
    }

    public function test_authenticated_user_is_redirected_away_from_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/register')->assertRedirect('/dashboard');
    }
}
