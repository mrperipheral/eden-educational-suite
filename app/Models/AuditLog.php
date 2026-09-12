<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable record of an administrative or security-relevant event
 * (Milestone 26, `docs/audit.md`). Every row is written exclusively through
 * `App\Services\Audit\AuditRecorder` — there is no `AuditLogRequest`, no
 * `$fillable`-driven controller write, and deliberately **no** update/
 * destroy route anywhere in the application. That absence, not a model-level
 * guard, is what makes an audit record immutable from the UI: there is
 * simply no feature that could change or remove one.
 *
 * **Not** `BelongsToSchool`: `school_id` is nullable (see the migration for
 * why) and every tenant-scoped query filters it explicitly
 * (`AuditLogController` always adds `where('school_id', TenantContext::
 * idOrFail())`) rather than relying on a global scope that would throw for
 * the genuinely-schoolless account-level security events.
 *
 * `auditable_type`/`auditable_id` reference the affected row **without** a
 * real foreign key — the target may later be hard-deleted, and the audit
 * entry must stay meaningful regardless (`auditable_label` is a
 * human-readable snapshot for exactly that reason). `changes` is a
 * redacted before/after diff, never raw credentials/secrets/tokens (see
 * `AuditRecorder::REDACTED_KEYS`).
 *
 * No `updated_at` — `UPDATED_AT` is null, since a row is never modified
 * after it is written.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'school_id',
        'actor_id',
        'actor_name',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'summary',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'))->orderByDesc($this->qualifyColumn('id'));
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(fn (Builder $q) => $q->where($this->qualifyColumn('summary'), 'like', $like)
            ->orWhere($this->qualifyColumn('actor_name'), 'like', $like)
            ->orWhere($this->qualifyColumn('auditable_label'), 'like', $like));
    }
}
