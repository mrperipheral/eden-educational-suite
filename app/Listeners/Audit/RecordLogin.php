<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\Login;

/**
 * Records a successful authentication. Fired by `Auth::attempt()` inside
 * `LoginRequest::authenticate()` — including the moment just before a
 * suspended/disabled account is immediately logged back out again, which is
 * why `LoginRequest` additionally records its own explicit
 * `auth.login.blocked` event right after this one fires (M26,
 * `docs/audit.md`).
 *
 * No tenant context exists yet at login (the route carries no `tenant`
 * middleware) — `AuditRecorder` records `school_id = null`, by design.
 */
class RecordLogin
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(Login $event): void
    {
        $this->recorder->record(
            event: 'auth.login.success',
            summary: __(':name signed in.', ['name' => $event->user->name]),
            actor: $event->user,
        );
    }
}
