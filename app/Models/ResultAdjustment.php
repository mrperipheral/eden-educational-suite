<?php

namespace App\Models;

use App\Enums\ResultAdjustmentStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ResultAdjustmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A controlled, auditable correction to one {@see StudentSubjectResult}'s
 * `percentage`. School-owned ({@see BelongsToSchool}) *and* scoped to its
 * subject result. See `docs/results-report-cards.md` §12.
 *
 * This is **not** a general edit endpoint. `status`
 * ({@see ResultAdjustmentStatus}) starts `pending` and has *no effect* until
 * an explicit {@see self::apply()} (which updates the parent subject result's
 * `percentage` and re-derives its grade — never lets a grade be typed over
 * directly) or {@see self::reject()} (no change). Both, like the proposal
 * itself, require `result.adjust`.
 */
class ResultAdjustment extends Model
{
    /** @use HasFactory<ResultAdjustmentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'field',
        'original_value',
        'adjusted_value',
        'reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'field' => 'percentage',
        'status' => ResultAdjustmentStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:2',
            'adjusted_value' => 'decimal:2',
            'status' => ResultAdjustmentStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === ResultAdjustmentStatus::Pending;
    }

    /**
     * Apply this adjustment: update the parent subject result's percentage,
     * re-derive its grade from the run's grading scheme, and mark it adjusted.
     * Does **not** recompute the run's overall ranking — the caller
     * (`App\Services\Results\ResultCompiler::recomputeRanking()`) does that
     * once, after the value actually changes.
     */
    public function apply(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultAdjustmentStatus::Applied;
            $this->decided_by = $by->getKey();
            $this->decided_at = Carbon::now();
            $this->save();

            $subjectResult = $this->subjectResult;
            $grade = $subjectResult->resultRun->gradingScheme->gradeFor((float) $this->adjusted_value);

            $subjectResult->percentage = $this->adjusted_value;
            $subjectResult->grading_scheme_grade_id = $grade?->id;
            $subjectResult->grade_code_snapshot = $grade?->code;
            $subjectResult->grade_remark_snapshot = $grade?->remark;
            $subjectResult->is_adjusted = true;
            $subjectResult->adjusted_by = $by->getKey();
            $subjectResult->adjusted_at = Carbon::now();
            $subjectResult->save();
        });
    }

    public function reject(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = ResultAdjustmentStatus::Rejected;
            $this->decided_by = $by->getKey();
            $this->decided_at = Carbon::now();
            $this->save();
        });
    }

    /**
     * @param  Builder<ResultAdjustment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('requested_at'));
    }
}
