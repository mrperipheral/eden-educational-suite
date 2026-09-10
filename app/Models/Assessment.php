<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Models\Concerns\HasClassRoster;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A gradeable assessment for one class in one subject. School-owned
 * ({@see BelongsToSchool}): every query is constrained to the active tenant,
 * `school_id` is stamped from the context and is never in `$fillable` / read
 * from input. See `docs/assessment-management.md`.
 *
 * The academic context ({@see AcademicSession} + {@see AcademicPeriod} +
 * {@see AcademicLevel} + {@see LevelArm} + {@see Subject}) is fixed at creation.
 * `status` / `published_at` / `locked_*` are **not** mass-assignable — they
 * change only through {@see self::publish()} / {@see self::unpublish()} /
 * {@see self::lock()} / {@see self::unlock()}.
 *
 * M14 stores source scores only. Final grades / averages / positions are M15's.
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use BelongsToSchool, HasClassRoster, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'assessment_category_id',
        'assignment_id',
        'title',
        'assessment_date',
        'max_score',
        'instructions',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AssessmentStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'max_score' => 'decimal:2',
            'status' => AssessmentStatus::class,
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function rosterDate(): string
    {
        return $this->assessment_date->toDateString();
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
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<AssessmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'assessment_category_id');
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return HasMany<AssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->status === AssessmentStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === AssessmentStatus::Published;
    }

    public function isLocked(): bool
    {
        return $this->status === AssessmentStatus::Locked;
    }

    public function acceptsScores(): bool
    {
        return $this->status->acceptsScores();
    }

    public function structureEditable(): bool
    {
        return $this->status->structureEditable();
    }

    /**
     * Register-level totals, from the loaded `scores` (no extra query when
     * eager-loaded).
     *
     * @return array{total: int, entered: int, unentered: int, highest: float|null, lowest: float|null}
     */
    public function summary(): array
    {
        $scores = $this->relationLoaded('scores') ? $this->scores : $this->scores()->get();
        $entered = $scores->filter(fn (AssessmentScore $s) => $s->score !== null);

        return [
            'total' => $scores->count(),
            'entered' => $entered->count(),
            'unentered' => $scores->count() - $entered->count(),
            'highest' => $entered->isEmpty() ? null : (float) $entered->max('score'),
            'lowest' => $entered->isEmpty() ? null : (float) $entered->min('score'),
        ];
    }

    /** The highest score currently recorded — the floor for a max-score reduction. */
    public function highestRecordedScore(): ?float
    {
        $value = $this->scores()->whereNotNull('score')->max('score');

        return $value === null ? null : (float) $value;
    }

    public function publish(): void
    {
        DB::transaction(function () {
            $this->status = AssessmentStatus::Published;
            $this->published_at ??= Carbon::now();
            $this->save();
        });
    }

    public function unpublish(): void
    {
        DB::transaction(function () {
            $this->status = AssessmentStatus::Draft;
            $this->published_at = null;
            $this->save();
        });
    }

    public function lock(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = AssessmentStatus::Locked;
            $this->locked_at = Carbon::now();
            $this->locked_by = $by->getKey();
            $this->save();
        });
    }

    /** Return a locked assessment to published so an authorized user can correct it. */
    public function unlock(): void
    {
        DB::transaction(function () {
            $this->status = AssessmentStatus::Published;
            $this->locked_at = null;
            $this->locked_by = null;
            $this->save();
        });
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('assessment_date'))->orderByDesc($this->qualifyColumn('id'));
    }
}
