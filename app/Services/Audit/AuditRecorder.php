<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;

/**
 * The single seam every module uses to write an audit event, without
 * knowing anything about the storage shape — mirrors `App\Services\
 * Notifications\NotificationDispatcher`'s role for M18. See `docs/audit.md`.
 *
 * `school_id` defaults to the active `TenantContext` (present for virtually
 * every business/administrative event, since those routes carry the
 * `tenant` middleware); an explicit `$schoolId` override exists only for the
 * one genuine exception — `Platform\SchoolController::store()`, which
 * creates a school *before* any tenant context can exist. Events with no
 * tenant context at all (login/logout/password reset/email verification —
 * their routes never carry `tenant`) are written with `school_id = null`,
 * by design; see the migration's docblock.
 *
 * `$actor` defaults to `auth()->user()`; a null actor is valid (an
 * unauthenticated failed login has none) and is recorded as such, never
 * defaulted to a fake system user.
 *
 * Every `changes` payload passes through {@see self::redact()} — a
 * blanket, key-name-based filter, not a per-model allow-list — so a future
 * caller can never forget to protect a newly added secret-shaped column.
 */
class AuditRecorder
{
    /**
     * Case-insensitive substrings; a key containing any of these is
     * replaced with `[redacted]` rather than omitted, so the shape of the
     * payload (which fields changed) stays visible without ever exposing
     * the value.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password',
        'remember_token',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>|null  $before  Raw attribute snapshot captured by the caller
     *                                             *before* mutating the model (e.g. `$model->getAttributes()`
     *                                             copied to a local var, or a small hand-built diff like
     *                                             `['role' => $oldRole?->value]`). Never derived from
     *                                             `getOriginal()` here — by the time this method runs, a
     *                                             model that has already been saved has re-synced its own
     *                                             "original" state, so the caller must snapshot first.
     * @param  array<string, mixed>|null  $after  The corresponding post-change values, same shape as $before.
     */
    public function record(
        string $event,
        string $summary,
        ?Model $auditable = null,
        ?string $auditableLabel = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
        ?int $schoolId = null,
    ): AuditLog {
        $actor ??= auth()->user();
        $request = RequestFacade::instance();

        return AuditLog::query()->create([
            'school_id' => $schoolId ?? $this->tenant->id(),
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'auditable_label' => $auditableLabel,
            'summary' => Str::limit($summary, 500, ''),
            'changes' => $this->buildChanges($before, $after),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() !== null ? Str::limit($request->userAgent(), 255, '') : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>|null}|null
     */
    private function buildChanges(?array $before, ?array $after): ?array
    {
        if ($before === null && $after === null) {
            return null;
        }

        return [
            'before' => $before !== null ? $this->redact($before) : null,
            'after' => $after !== null ? $this->redact($after) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function redact(array $attributes): array
    {
        $redacted = [];

        foreach ($attributes as $key => $value) {
            $redacted[$key] = $this->isSensitiveKey((string) $key) ? '[redacted]' : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
