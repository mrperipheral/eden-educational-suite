<?php

namespace App\Models;

use App\Enums\EntryAssessmentStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\EntryAssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A record of an assessment conducted for a prospective or newly admitted
 * student. School-owned ({@see BelongsToSchool}): every query is constrained
 * to the active tenant, `school_id` is stamped from the context and is
 * never in `$fillable` / read from input. See
 * `docs/entry-placement-assessment.md`.
 *
 * **This records the assessment, not a placement decision.** There is no
 * recommended class/arm, no placement workflow, and nothing here ever
 * changes a student's enrolment automatically.
 *
 * `candidate_name` is always stored explicitly, regardless of whether
 * {@see self::student()} is set — a self-contained historical record that
 * never depends on a `Student` row existing or keeping the same name later.
 * One record covers one subject; a candidate assessed in several subjects
 * gets several rows sharing the same candidate/admission-reference/date.
 *
 * `assessor_id` and `status` are **not** mass-assignable — the assessor is
 * captured from the authenticated user at creation
 * (`EntryAssessmentController::store()`), and `status`
 * ({@see EntryAssessmentStatus}) changes only through {@see self::archive()}
 * / {@see self::restore()}. The row is never hard-deleted.
 */
class EntryAssessment extends Model
{
    /** @use HasFactory<EntryAssessmentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'candidate_name',
        'admission_reference',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'assessed_on',
        'score',
        'max_score',
        'result',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => EntryAssessmentStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessed_on' => 'date',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'status' => EntryAssessmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    /**
     * The score as a percentage of `max_score`, or `null` when no score has
     * been entered yet. Deliberately computed, never stored — `bcmath`
     * against the `decimal` string attributes, never native float division.
     */
    public function percentage(): ?string
    {
        if ($this->score === null || bccomp((string) $this->max_score, '0', 2) <= 0) {
            return null;
        }

        return bcdiv(bcmul((string) $this->score, '100', 4), (string) $this->max_score, 2);
    }

    public function isArchived(): bool
    {
        return $this->status === EntryAssessmentStatus::Archived;
    }

    /** Retire this record from the active working set. Never a hard delete. */
    public function archive(): void
    {
        DB::transaction(function () {
            $this->status = EntryAssessmentStatus::Archived;
            $this->save();
        });
    }

    /** Return an archived record to the active working set. */
    public function restore(): void
    {
        DB::transaction(function () {
            $this->status = EntryAssessmentStatus::Active;
            $this->save();
        });
    }

    /**
     * @param  Builder<EntryAssessment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('assessed_on'))->orderByDesc($this->qualifyColumn('id'));
    }

    /**
     * @param  Builder<EntryAssessment>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(fn (Builder $q) => $q->where($this->qualifyColumn('candidate_name'), 'like', $like)
            ->orWhere($this->qualifyColumn('admission_reference'), 'like', $like));
    }
}
