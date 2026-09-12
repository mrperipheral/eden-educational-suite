<?php

namespace App\Listeners\Audit;

use App\Services\Audit\AuditRecorder;
use Illuminate\Auth\Events\Verified;

/**
 * Records a confirmed email address (`VerifyEmailController` fires this
 * Laravel-native event once, the first time a signed verification link is
 * followed) (M26, `docs/audit.md`).
 */
class RecordEmailVerified
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function handle(Verified $event): void
    {
        $this->recorder->record(
            event: 'auth.email.verified',
            summary: __(':name verified their email address.', ['name' => $event->user->name]),
            actor: $event->user,
        );
    }
}
