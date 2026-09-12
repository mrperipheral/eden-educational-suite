<?php

namespace App\Models;

use App\Enums\ExaminationStatus;
use App\Enums\ResultReleaseMode;
use App\Models\Concerns\HasClassRoster;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A school-scoped online examination (Milestone 23, `docs/cbt.md`).
 * Academic context (session + period + level + arm + subject) is fully
 * required and fixed at creation, mirroring `App\Models\Assessment`
 * exactly — one class's exam, not a "whole level" broadcast.
 *
 * `status` / `scheduled_at` / `closed_at` / `created_by` are **not**
 * mass-assignable — changed only through {@see self::schedule()} /
 * {@see self::close()}, mirroring `Assessment::publish()`/`lock()`.
 */
class Examination extends Model
{
    /** @use HasFactory<ExaminationFactory> */
    use BelongsToSchool, HasClassRoster, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'title',
        'description',
        'duration_minutes',
        'starts_at',
        'ends_at',
        'pass_mark_percentage',
        'result_release',
        'result_release_at',
    ];

    /**
     * `status` defaults to its DB-column default (`draft`) — set here too
     * so a freshly-`new`-ed instance has it available in memory
     * immediately, without a round-trip reload, mirroring
     * `App\Models\TeacherAssignment`'s own convention.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExaminationStatus::class,
            'result_release' => ResultReleaseMode::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'result_release_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'closed_at' => 'datetime',
            'pass_mark_percentage' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ExaminationQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ExaminationQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function rosterDate(): string
    {
        return $this->starts_at->toDateString();
    }

    /** Total marks available — the sum of every attached question's snapshotted marks. */
    public function totalMarks(): string
    {
        return (string) $this->questions()->sum('marks');
    }

    /** Whether the server clock currently falls within the exam's own start/end window. */
    public function isWithinWindow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $now->gte($this->starts_at) && $now->lte($this->ends_at);
    }

    /** Whether a student could start (or resume) an attempt right now. */
    public function isOpenForAttempts(?Carbon $now = null): bool
    {
        return $this->status === ExaminationStatus::Scheduled && $this->isWithinWindow($now);
    }

    /**
     * Draft → Scheduled. Requires at least one question — makes the exam
     * visible/startable to its class, and freezes its own structure and
     * metadata from further editing.
     *
     * @throws RuntimeException
     */
    public function schedule(): void
    {
        if ($this->status !== ExaminationStatus::Draft) {
            throw new RuntimeException('Only a draft examination can be scheduled.');
        }

        if ($this->questions()->count() === 0) {
            throw new RuntimeException('An examination needs at least one question before it can be scheduled.');
        }

        DB::transaction(function () {
            $this->status = ExaminationStatus::Scheduled;
            $this->scheduled_at = Carbon::now();
            $this->save();
        });
    }

    /**
     * Scheduled → Closed. Immediately stops any further attempts from
     * starting; already-in-progress attempts are still individually
     * finalised the next time they are touched (by their own expiry
     * check), not force-submitted here.
     *
     * @throws RuntimeException
     */
    public function close(): void
    {
        if ($this->status !== ExaminationStatus::Scheduled) {
            throw new RuntimeException('Only a scheduled examination can be closed.');
        }

        DB::transaction(function () {
            $this->status = ExaminationStatus::Closed;
            $this->closed_at = Carbon::now();
            $this->save();
        });
    }

    /**
     * @param  Builder<Examination>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('starts_at'));
    }
}
