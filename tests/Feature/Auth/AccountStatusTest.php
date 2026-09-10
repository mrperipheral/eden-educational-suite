<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_account_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $user = User::factory()->disabled()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_account_suspended_mid_session_is_ejected_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withoutVite()->get('/dashboard')->assertOk();

        // `status` is intentionally not mass-assignable; an admin flow sets it directly.
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_status_check_is_server_side_not_dependent_on_ui(): void
    {
        // Even hitting a non-UI endpoint, a disabled account is rejected.
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertRedirect('/login');
    }
}
