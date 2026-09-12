<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records a completed password reset via the forgot-password email flow
 * (`NewPasswordController::store()` fires this Laravel-native event on
 * success). Distinct from `auth.password.changed` (a signed-in user
 * changing their own password from Settings) — see
 * `App\Listeners\Audit\RecordPasswordChange` (M26, `docs/audit.md`).
 */
class RecordPasswordReset
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(PasswordReset $event): void
    {
        $this->recorder->record(
            event: 'auth.password.reset',
            summary: __(':name reset their password via the forgot-password link.', ['name' => $event->user->name]),
            actor: $event->user,
        );
    }
}
