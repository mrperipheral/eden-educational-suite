<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\Failed;

/**
 * Records a failed authentication attempt — wrong password, or an email
 * with no matching account. Fired by `Auth::attempt()` inside
 * `LoginRequest::authenticate()` (M26, `docs/audit.md`).
 *
 * `Failed::$credentials` carries the **raw submitted password** — never
 * read here. Only the attempted email is recorded (in the summary text,
 * never as a queryable "attempted email" column, to avoid quietly building
 * an account-enumeration oracle out of the audit viewer). `Failed::$user`
 * is the matched account when the email was real but the password was
 * wrong, `null` when the email matched nobody — either way there is no
 * authenticated actor, so `actor_id` stays null (a genuine "not yet
 * identifiable" case, not a bug).
 */
class RecordFailedLogin
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? $event->credentials['email'] : '(unknown)';

        $this->recorder->record(
            event: 'auth.login.failed',
            summary: __('Failed sign-in attempt for :email.', ['email' => $email]),
            actor: null,
        );
    }
}
