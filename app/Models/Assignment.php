<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Models\Concerns\HasClassRoster;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A piece of set work for a class in a subject. School-owned
 * ({@see BelongsToSchool}). The academic context is fixed at creation.
 *
 * An assignment carries **no scores** — it tracks *completion* only, via
 * {@see AssignmentSubmission}. It may later be graded by an {@see Assessment}
 * that references it, but M14 computes nothing from that link.
 *
 * `status` / `published_at` are **not** mass-assignable — they move through
 * {@see self::publish()} / {@see self::unpublish()} / {@see self::close()} /
 * {@see self::reopen()}.
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use BelongsToSchool, HasClassRoster, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'title',
        'instructions',
        'assigned_on',
        'due_on',
        'max_score',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AssignmentStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'due_on' => 'date',
            'max_score' => 'decimal:2',
            'status' => AssignmentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function rosterDate(): string
    {
        return $this->assigned_on->toDateString();
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
     * The teacher who owns this assignment, if the creator has a teacher record.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isDraft(): bool
    {
        return $this->status === AssignmentStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === AssignmentStatus::Published;
    }

    public function isClosed(): bool
    {
        return $this->status === AssignmentStatus::Closed;
    }

    public function structureEditable(): bool
    {
        return $this->status->structureEditable();
    }

    public function tracksCompletion(): bool
    {
        return $this->status->tracksCompletion();
    }

    /**
     * @return array{total: int, submitted: int, late: int, exempt: int, pending: int}
     */
    public function summary(): array
    {
        $submissions = $this->relationLoaded('submissions') ? $this->submissions : $this->submissions()->get();
        $byStatus = $submissions->countBy(fn (AssignmentSubmission $s) => $s->status->value);

        return [
            'total' => $submissions->count(),
            'submitted' => $byStatus->get('submitted', 0),
            'late' => $byStatus->get('late', 0),
            'exempt' => $byStatus->get('exempt', 0),
            'pending' => $byStatus->get('pending', 0),
        ];
    }

    public function publish(): void
    {
        DB::transaction(function () {
            $this->status = AssignmentStatus::Published;
            $this->published_at ??= Carbon::now();
            $this->save();
        });
    }

    public function unpublish(): void
    {
        DB::transaction(function () {
            $this->status = AssignmentStatus::Draft;
            $this->published_at = null;
            $this->save();
        });
    }

    public function close(): void
    {
        DB::transaction(function () {
            $this->status = AssignmentStatus::Closed;
            $this->save();
        });
    }

    public function reopen(): void
    {
        DB::transaction(function () {
            $this->status = AssignmentStatus::Published;
            $this->save();
        });
    }

    /**
     * @param  Builder<Assignment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('due_on'))->orderByDesc($this->qualifyColumn('id'));
    }
}
