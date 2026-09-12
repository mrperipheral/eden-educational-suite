<?php

namespace Tests\Feature\Audit;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Authentication/security audit events (M26, `docs/audit.md`) — these run
 * on account-level routes with no active tenant context, so `school_id` is
 * always null by design (see the `audit_logs` migration).
 */
class AuditAuthenticationTest extends AuditTestCase
{
    public function test_a_successful_login_is_audited(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $log = $this->latestAuditFor('auth.login.success');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertNull($log->school_id);
    }

    public function test_a_failed_login_is_audited_without_leaking_the_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $log = $this->latestAuditFor('auth.login.failed');
        $this->assertNotNull($log);
        $this->assertNull($log->actor_id);
        $this->assertStringContainsString($user->email, $log->summary);
        $payload = json_encode($log->toArray());
        $this->assertStringNotContainsString('wrong-password', $payload);
        $this->assertStringNotContainsString('correct-password', $payload);
    }

    public function test_a_login_attempt_for_an_unknown_email_is_audited(): void
    {
        $this->post('/login', ['email' => 'nobody-here@example.test', 'password' => 'whatever']);

        $log = $this->latestAuditFor('auth.login.failed');
        $this->assertNotNull($log);
        $this->assertNull($log->actor_id);
        $this->assertStringContainsString('nobody-here@example.test', $log->summary);
    }

    public function test_a_suspended_account_login_attempt_is_audited_as_blocked(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password'), 'status' => UserStatus::Suspended->value]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $log = $this->latestAuditFor('auth.login.blocked');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/logout');

        $log = $this->latestAuditFor('auth.logout');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertNull($log->school_id);
    }

    public function test_a_completed_password_reset_is_audited(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $token = $notification->token;

            $this->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'a-new-strong-password-1',
                'password_confirmation' => 'a-new-strong-password-1',
            ])->assertRedirect(route('login'));

            return true;
        });

        $log = $this->latestAuditFor('auth.password.reset');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
        $payload = json_encode($log->toArray());
        $this->assertStringNotContainsString('a-new-strong-password-1', $payload);
    }

    public function test_email_verification_is_audited(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url);

        $log = $this->latestAuditFor('auth.email.verified');
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->actor_id);
    }
}
