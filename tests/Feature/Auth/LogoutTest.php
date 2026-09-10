<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_after_logout_protected_routes_are_unreachable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->get('/dashboard')->assertRedirect('/login');
    }
}
