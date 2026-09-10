<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_notice_renders_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->withoutVite()->get('/verify-email')
            ->assertOk()
            ->assertSee('Verify your email');
    }

    public function test_unverified_user_cannot_reach_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified_with_a_valid_signed_link(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($url);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard').'?verified=1');
    }

    public function test_email_is_not_verified_with_an_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email@example.com')]
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get("/verify-email/{$user->id}/".sha1($user->email))
            ->assertForbidden();
    }

    public function test_already_verified_user_visiting_notice_is_sent_to_dashboard(): void
    {
        $user = User::factory()->create(); // verified by default

        $this->actingAs($user)->get('/verify-email')->assertRedirect(route('dashboard'));
    }

    public function test_verification_email_can_be_resent(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertSessionHas('status');
    }
}
