<?php

namespace App\Models;

use App\Enums\ResultRunStatus;
use App\Models\Concerns\HasClassRoster;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ResultRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A result run — one class's compiled results for one term. School-owned
 * ({@see BelongsToSchool}). Scoped to `(academic_session, academic_period,
 * academic_level, level_arm)`, fixed at creation — a run never spans terms.
 * See `docs/results-report-cards.md`.
 *
 * `status` (`App\Enums\ResultRunStatus`) and every `*_by` / `*_at` pair are
 * **not** mass-assignable — they change only through the lifecycle methods
 * below, called from `App\Services\Results\ResultCompiler` / the controller.
 * Compilation itself (computing {@see StudentResult} / {@see StudentSubjectResult})
 * is the compiler's job, not the model's.
 */
class ResultRun extends Model
{
    /** @use HasFactory<ResultRunFactory> */
    use BelongsToSchool, HasClassRoster, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'grading_scheme_id',
        'result_weighting_scheme_id',
        'ranking_enabled',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ResultRunStatus::Draft->value,
        'ranking_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ranking_enabled' => 'boolean',
            'status' => ResultRunStatus::class,
            'compiled_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * The class roster's reference date — the term's own end date, so
     * eligibility is deterministic and reproducible regardless of when the
     * run is (re)compiled: "who was in this class through this term."
     */
    public function rosterDate(): string
    {
        return $this->period->ends_on->toDateString();
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
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    /**
     * @return BelongsTo<LevelArm, $this>
     */
    public function arm(): BelongsTo
    {
        return $this->belongsTo(LevelArm::class, 'level_arm_id');
    }

    /**
     * @return BelongsTo<GradingScheme, $this>
     */
    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /**
     * @return BelongsTo<ResultWeightingScheme, $this>
     */
    public function weightingScheme(): BelongsTo
    {
        return $this->belongsTo(ResultWeightingScheme::class, 'result_weighting_scheme_id');
    }

    /**
     * @return HasMany<StudentResult, $this>
     */
    public function studentResults(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }

    /**
     * @return HasMany<StudentSubjectResult, $this>
     */
    public function subjectResults(): HasMany
    {
        return $this->hasMany(StudentSubjectResult::class);
    }

    /**
     * The frozen report-card field-toggle snapshot taken when this run was
     * first published, if any — see {@see ReportCardConfiguration::snapshotForRun()}.
     *
     * @return HasOne<ReportCardConfiguration, $this>
     */
    public function reportCardSnapshot(): HasOne
    {
        return $this->hasOne(ReportCardConfiguration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function compiledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isDraft(): bool
    {
        return $this->status === ResultRunStatus::Draft;
    }

    public function isCompiled(): bool
    {
        return $this->status === ResultRunStatus::Compiled;
    }

    public function isReviewed(): bool
    {
        return $this->status === ResultRunStatus::Reviewed;
    }

    public function isApproved(): bool
    {
        return $this->status === ResultRunStatus::Approved;
    }

    public function isPublished(): bool
    {
        return $this->status === ResultRunStatus::Published;
    }

    public function isLocked(): bool
    {
        return $this->status === ResultRunStatus::Locked;
    }

    public function recompilable(): bool
    {
        return $this->status->recompilable();
    }

    public function requiresAdjustment(): bool
    {
        return $this->status->requiresAdjustment();
    }

    /** Stamped by the compiler after a successful compile; always moves to `compiled` (undoes a prior review). */
    public function markCompiled(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultRunStatus::Compiled;
            $this->compiled_by = $by->getKey();
            $this->compiled_at = Carbon::now();
            $this->save();
        });
    }

    public function review(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultRunStatus::Reviewed;
            $this->reviewed_by = $by->getKey();
            $this->reviewed_at = Carbon::now();
            $this->save();
        });
    }

    public function approve(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultRunStatus::Approved;
            $this->approved_by = $by->getKey();
            $this->approved_at = Carbon::now();
            $this->save();
        });
    }

    public function publish(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultRunStatus::Published;
            $this->published_by = $by->getKey();
            $this->published_at = Carbon::now();
            $this->save();
        });
    }

    public function lock(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultRunStatus::Locked;
            $this->locked_by = $by->getKey();
            $this->locked_at = Carbon::now();
            $this->save();
        });
    }

    /**
     * @param  Builder<ResultRun>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('id'));
    }
}
