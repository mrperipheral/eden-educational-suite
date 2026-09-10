<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withoutVite()->get('/confirm-password')
            ->assertOk()
            ->assertSee('Confirm your password');
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/confirm-password', [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');
    }

    public function test_sensitive_route_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        // Without confirmation: bounced to the confirm screen.
        $this->actingAs($user)->delete('/settings/profile')
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_sensitive_route_proceeds_after_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/confirm-password', ['password' => 'password']);

        $this->actingAs($user)->delete('/settings/profile')->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertGuest();
    }
}
