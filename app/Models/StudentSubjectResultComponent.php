<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\StudentSubjectResultComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One category's contribution to a {@see StudentSubjectResult} — the "CA1
 * 8/10, Exam 55/70" line items a report card shows. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its subject result. Written only by
 * `App\Services\Results\ResultCompiler`.
 *
 * `category_name_snapshot` / `weight_percentage_snapshot` freeze the label and
 * weight as compiled — a later rename of the category or edit of the
 * weighting scheme never rewrites a historical row.
 */
class StudentSubjectResultComponent extends Model
{
    /** @use HasFactory<StudentSubjectResultComponentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'assessment_category_id',
        'category_name_snapshot',
        'weight_percentage_snapshot',
        'raw_score',
        'raw_max_score',
        'score_percentage',
        'weighted_contribution',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_percentage_snapshot' => 'decimal:2',
            'raw_score' => 'decimal:2',
            'raw_max_score' => 'decimal:2',
            'score_percentage' => 'decimal:2',
            'weighted_contribution' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StudentSubjectResult, $this>
     */
    public function subjectResult(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectResult::class, 'student_subject_result_id');
    }

    /**
     * @return BelongsTo<AssessmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'assessment_category_id');
    }

    /**
     * @param  Builder<StudentSubjectResultComponent>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'));
    }
}
