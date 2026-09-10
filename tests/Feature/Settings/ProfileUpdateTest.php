<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withoutVite()->get('/settings/profile')
            ->assertOk()
            ->assertSee('Profile information')
            ->assertSee('Update password')
            ->assertSee('Delete account');
    }

    public function test_profile_page_requires_authentication(): void
    {
        $this->get('/settings/profile')->assertRedirect('/login');
    }

    public function test_name_and_email_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }

    public function test_changing_email_resets_verification_state(): void
    {
        $user = User::factory()->create();
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'changed@example.com',
        ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_keeping_the_same_email_does_not_reset_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Same Email',
            'email' => $user->email,
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_profile_update_validates_input(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->from('/settings/profile')->patch('/settings/profile', [
            'name' => '',
            'email' => 'taken@example.com',
        ])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_password_can_be_updated_with_correct_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'brand-new-password-9',
            'password_confirmation' => 'brand-new-password-9',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-password-9', $user->fresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/settings/profile')->put('/settings/password', [
            'current_password' => 'not-the-password',
            'password' => 'brand-new-password-9',
            'password_confirmation' => 'brand-new-password-9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
