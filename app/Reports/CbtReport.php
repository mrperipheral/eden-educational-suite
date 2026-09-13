<?php

namespace App\Reports;

use App\Enums\ExamAttemptStatus;
use App\Enums\Permission;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Models\User;
use App\Support\Reports\ReportAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * CBT (M23) + Question Bank (M24) reporting — examination summary and its
 * attempt list. This is a **staff-only** surface (gated `cbt.view`, never
 * reachable by a student) — staff already see attempt scores/percentages/
 * pass-fail unconditionally in the existing `ExaminationAttemptController`
 * (no `isResultVisible()` gate there), and this report mirrors that
 * exactly: it reads persisted `ExamAttempt.score`/`percentage`/`passed`
 * columns directly, never recomputes them, and never touches
 * `ExaminationQuestionOption.is_correct` at all. See `docs/reporting.md`
 * §9.
 */
class CbtReport
{
    public function __construct(private readonly ReportAuthorizer $authorizer) {}

    /**
     * @param  array{session?:int,period?:int,level?:int,arm?:int,subject?:int,status?:string}  $filters
     */
    public function examinationSummary(array $filters, User $user): LengthAwarePaginator
    {
        return $this->examinationSummaryQuery($filters, $user)->paginate(15)->withQueryString();
    }

    /**
     * The exact query `examinationSummary()` paginates — exposed for the
     * export action to `chunk()`.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,subject?:int,status?:string}  $filters
     * @return Builder<Examination>
     */
    public function examinationSummaryQuery(array $filters, User $user): Builder
    {
        $query = Examination::query()
            ->with(['session:id,name', 'period:id,name', 'level:id,name', 'arm:id,name', 'subject:id,name'])
            ->withCount([
                'attempts',
                'attempts as completed_attempts_count' => fn (Builder $q) => $q->where('status', ExamAttemptStatus::Completed->value),
                'attempts as passed_attempts_count' => fn (Builder $q) => $q->where('status', ExamAttemptStatus::Completed->value)->where('passed', true),
            ])
            ->withAvg(['attempts as average_percentage' => fn (Builder $q) => $q->where('status', ExamAttemptStatus::Completed->value)], 'percentage')
            ->ordered();

        $this->applyFilters($query, $filters);
        $this->scopeToTeacher($query, $user);

        return $query;
    }

    /**
     * The attempt list for one examination — tenant-scoped `findOrFail` is
     * the caller's responsibility (the controller resolves `$examination`
     * before calling this).
     */
    public function attempts(Examination $examination): LengthAwarePaginator
    {
        return $examination->attempts()
            ->with('student:id,first_name,middle_name,last_name,preferred_name,admission_number')
            ->ordered()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  Builder<Examination>  $query
     * @param  array{session?:int,period?:int,level?:int,arm?:int,subject?:int,status?:string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['session'])) {
            $query->where('academic_session_id', $filters['session']);
        }
        if (! empty($filters['period'])) {
            $query->where('academic_period_id', $filters['period']);
        }
        if (! empty($filters['level'])) {
            $query->where('academic_level_id', $filters['level']);
        }
        if (! empty($filters['arm'])) {
            $query->where('level_arm_id', $filters['arm']);
        }
        if (! empty($filters['subject'])) {
            $query->where('subject_id', $filters['subject']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * A Teacher without `cbt.manage` sees only examinations for classes/
     * subjects they hold an active M11 assignment for.
     *
     * @param  Builder<Examination>  $query
     */
    private function scopeToTeacher(Builder $query, User $user): void
    {
        if ($user->hasPermission(Permission::CbtManage)) {
            return;
        }

        $levelIds = $this->authorizer->levelIdsFor($user);
        $subjectIds = $this->authorizer->subjectIdsFor($user);

        if ($levelIds === [] || $subjectIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('academic_level_id', $levelIds)->whereIn('subject_id', $subjectIds);
    }
}
