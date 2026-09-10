<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_renders(): void
    {
        $this->withoutVite()->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_new_user_can_register(): void
    {
        Event::fake([Registered::class]);

        $response = $this->post('/register', [
            'name' => 'Ada Obi',
            'email' => 'ada@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));

        $user = User::whereEmail('ada@example.com')->firstOrFail();
        $this->assertSame('Ada Obi', $user->name);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->email_verified_at, 'new accounts start unverified');
        Event::assertDispatched(Registered::class);
    }

    public function test_password_is_hashed_not_stored_plaintext(): void
    {
        $this->post('/register', [
            'name' => 'Hash Me',
            'email' => 'hash@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $user = User::whereEmail('hash@example.com')->firstOrFail();
        $this->assertNotSame('password1234', $user->password);
        $this->assertTrue(Hash::check('password1234', $user->password));
    }

    public function test_registration_requires_valid_input(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertredirect('/register');
        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Second',
            'email' => 'taken@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_status_cannot_be_mass_assigned_during_registration(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
            'status' => 'disabled',
        ]);

        $user = User::whereEmail('sneaky@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Active, $user->status);
    }
}
