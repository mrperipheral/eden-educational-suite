<?php

namespace App\Models;

use App\Enums\TimetableStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\TimetableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A weekly timetable / schedule. School-owned ({@see BelongsToSchool}): every
 * query is constrained to the active tenant, `school_id` is stamped from the
 * context and is never in `$fillable` / read from input. See
 * `docs/timetable-management.md`.
 *
 * Scoped to one {@see AcademicSession} (and optionally one {@see AcademicPeriod});
 * the scope is fixed at creation so the lessons ({@see self::entries()}) can
 * never cross sessions. `status` ({@see TimetableStatus}) and `published_at` are
 * **not** mass-assignable — they change only through
 * `TimetableController::updateStatus()`, which refuses to publish a timetable
 * that still has a scheduling conflict.
 */
class Timetable extends Model
{
    /** @use HasFactory<TimetableFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'name',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TimetableStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TimetableStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function isPublished(): bool
    {
        return $this->status === TimetableStatus::Published;
    }

    /** Mark the timetable published. Callers must confirm it is conflict-free first. */
    public function publish(): void
    {
        DB::transaction(function () {
            $this->status = TimetableStatus::Published;
            $this->published_at ??= Carbon::now();
            $this->save();
        });
    }

    /** Return the timetable to draft. */
    public function unpublish(): void
    {
        DB::transaction(function () {
            $this->status = TimetableStatus::Draft;
            $this->published_at = null;
            $this->save();
        });
    }

    /**
     * @param  Builder<Timetable>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), TimetableStatus::Published->value);
    }

    /**
     * @param  Builder<Timetable>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'))->orderByDesc($this->qualifyColumn('id'));
    }
}
