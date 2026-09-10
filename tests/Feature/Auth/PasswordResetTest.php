<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_renders(): void
    {
        $this->withoutVite()->get('/forgot-password')->assertOk()->assertSee('Reset your password');
    }

    public function test_reset_link_is_emailed_for_a_known_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_request_for_unknown_address_does_not_error_or_enumerate(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertNotSame('new-password-123', $user->password);

        // Token is single-use: it must not work a second time.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-password-456',
            'password_confirmation' => 'another-password-456',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/reset-password/bogus')->post('/reset-password', [
            'token' => 'totally-invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_request_is_throttled(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 7) as $i) {
            $response = $this->post('/forgot-password', ['email' => $user->email]);
        }

        $response->assertStatus(429);
    }
}
