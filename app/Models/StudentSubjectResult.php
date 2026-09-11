<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\StudentSubjectResultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's compiled result for one subject within a {@see ResultRun}.
 * School-owned ({@see BelongsToSchool}) *and* scoped to its run. Written only
 * by `App\Services\Results\ResultCompiler`, except `percentage` /
 * `grade_*_snapshot` / `is_adjusted` / `adjusted_*`, which an *applied*
 * {@see ResultAdjustment} may change once the run requires the adjustment
 * workflow. See `docs/results-report-cards.md`.
 */
class StudentSubjectResult extends Model
{
    /** @use HasFactory<StudentSubjectResultFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'percentage',
        'grading_scheme_grade_id',
        'grade_code_snapshot',
        'grade_remark_snapshot',
        'subject_position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'subject_position' => 'integer',
            'is_adjusted' => 'boolean',
            'adjusted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ResultRun, $this>
     */
    public function resultRun(): BelongsTo
    {
        return $this->belongsTo(ResultRun::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradingSchemeGrade, $this>
     */
    public function gradingSchemeGrade(): BelongsTo
    {
        return $this->belongsTo(GradingSchemeGrade::class);
    }

    /**
     * @return HasMany<StudentSubjectResultComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(StudentSubjectResultComponent::class);
    }

    /**
     * @return HasMany<ResultAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(ResultAdjustment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /**
     * @param  Builder<StudentSubjectResult>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('subject_id'));
    }
}
