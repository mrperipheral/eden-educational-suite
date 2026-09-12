<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\Logout;

/**
 * Records a sign-out — both a genuine `POST /logout` and the app's own
 * forced logout of a suspended/disabled account mid-request (`EnsureAccountIsActive`).
 * `Logout::$user` is nullable in Laravel's own event signature (a logout
 * call with no resolved user is possible in principle) — skipped silently
 * when null, since there is nothing to attribute (M26, `docs/audit.md`).
 */
class RecordLogout
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->recorder->record(
            event: 'auth.logout',
            summary: __(':name signed out.', ['name' => $event->user->name]),
            actor: $event->user,
        );
    }
}
