<?php

namespace App\Reports;

use App\Enums\Permission;
use App\Enums\ResultRunStatus;
use App\Models\AcademicLevel;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Models\User;
use App\Support\Reports\ReportAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Academic (M15 results) reporting — student performance, subject
 * performance, class/arm performance, result-run summary. Every method
 * reads **already-compiled, frozen** `StudentResult`/`StudentSubjectResult`
 * columns (percentages, positions, grade snapshots) — never recomputes a
 * grade or a rank; see `docs/reporting.md` §3. A locked/published run's
 * numbers are read-only here, exactly as everywhere else in the app.
 */
class AcademicReport
{
    public function __construct(private readonly ReportAuthorizer $authorizer) {}

    /**
     * @param  array{session?:int,period?:int,level?:int,arm?:int,status?:string}  $filters
     */
    public function resultRunSummary(array $filters, User $user): LengthAwarePaginator
    {
        return $this->resultRunSummaryQuery($filters, $user)->paginate(20)->withQueryString();
    }

    /**
     * The exact query `resultRunSummary()` paginates — exposed for the
     * export action to `chunk()`.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,status?:string}  $filters
     * @return Builder<ResultRun>
     */
    public function resultRunSummaryQuery(array $filters, User $user): Builder
    {
        $query = ResultRun::query()
            ->with(['session:id,name', 'period:id,name', 'level:id,name', 'arm:id,name'])
            ->withCount('studentResults')
            ->ordered();

        $this->applyRunFilters($query, $filters);
        $this->scopeToTeacher($query, $user, 'academic_level_id', 'level_arm_id');

        return $query;
    }

    /**
     * @param  array{session?:int,period?:int,level?:int,arm?:int,run?:int}  $filters
     */
    public function studentPerformance(array $filters, User $user): LengthAwarePaginator
    {
        return $this->studentPerformanceQuery($filters, $user)->paginate(25)->withQueryString();
    }

    /**
     * The exact query `studentPerformance()` paginates — exposed
     * separately so the export action can `chunk()` the same filtered,
     * tenant-scoped, teacher-scoped result set instead of loading every
     * row into memory at once.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,run?:int}  $filters
     * @return Builder<StudentResult>
     */
    public function studentPerformanceQuery(array $filters, User $user): Builder
    {
        $query = StudentResult::query()
            ->with(['student:id,first_name,middle_name,last_name,preferred_name,admission_number', 'resultRun:id,status'])
            ->whereHas('resultRun', fn (Builder $q) => $q->whereIn('status', [ResultRunStatus::Published->value, ResultRunStatus::Locked->value]))
            ->ordered();

        $this->applyResultFilters($query, $filters);
        $this->scopeToTeacher($query, $user, 'academic_level_id', 'level_arm_id');

        return $query;
    }

    /**
     * Per-subject aggregate: how many students assessed, average %, and a
     * grade-code distribution (from the frozen `grade_code_snapshot` —
     * never re-derived from a scheme that may have changed since).
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,run?:int}  $filters
     * @return Collection<int, array{subject_id:int, subject_name:?string, students_assessed:int, average_percentage:?float, grades: array<string,int>}>
     */
    public function subjectPerformance(array $filters, User $user): Collection
    {
        $base = StudentSubjectResult::query()
            ->whereHas('resultRun', fn (Builder $q) => $q->whereIn('status', [ResultRunStatus::Published->value, ResultRunStatus::Locked->value]));

        $this->applyResultFilters($base, $filters);
        $this->scopeToTeacher($base, $user, 'academic_level_id', 'level_arm_id', 'subject_id');

        $summaries = (clone $base)
            ->selectRaw('subject_id, COUNT(*) as students_assessed, AVG(percentage) as average_percentage')
            ->groupBy('subject_id')
            ->with('subject:id,name')
            ->get();

        $gradeCounts = (clone $base)
            ->selectRaw('subject_id, grade_code_snapshot, COUNT(*) as total')
            ->whereNotNull('grade_code_snapshot')
            ->groupBy('subject_id', 'grade_code_snapshot')
            ->get()
            ->groupBy('subject_id');

        return $summaries->map(function (StudentSubjectResult $row) use ($gradeCounts) {
            $grades = ($gradeCounts->get($row->subject_id) ?? collect())
                ->mapWithKeys(fn ($g) => [$g->grade_code_snapshot => (int) $g->total])
                ->all();

            return [
                'subject_id' => $row->subject_id,
                'subject_name' => $row->subject?->name,
                'students_assessed' => (int) $row->students_assessed,
                'average_percentage' => $row->average_percentage !== null ? round((float) $row->average_percentage, 2) : null,
                'grades' => $grades,
            ];
        })->sortBy('subject_name')->values();
    }

    /**
     * Per-class/arm aggregate over `StudentResult` (the per-student overall
     * summary row) — average, class size, and a grade-code distribution
     * from `overall_grade_code_snapshot`.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,run?:int}  $filters
     * @return Collection<int, array{academic_level_id:int, level_arm_id:?int, level_name:?string, arm_name:?string, student_count:int, average_percentage:?float, grades: array<string,int>}>
     */
    public function classPerformance(array $filters, User $user): Collection
    {
        $base = StudentResult::query()
            ->whereHas('resultRun', fn (Builder $q) => $q->whereIn('status', [ResultRunStatus::Published->value, ResultRunStatus::Locked->value]));

        $this->applyResultFilters($base, $filters);
        $this->scopeToTeacher($base, $user, 'academic_level_id', 'level_arm_id');

        // selectRaw()+groupBy() here only ever selects the grouped/
        // aggregated columns — `result_run_id` is deliberately not one of
        // them, so eager-loading through it (`->with('resultRun...')`)
        // would fail under this app's strict-model setting (no missing
        // attributes). Level/arm names are looked up directly instead, by
        // the small set of distinct ids the aggregate actually returned —
        // one extra bounded query, never N+1.
        $summaries = (clone $base)
            ->selectRaw('academic_level_id, level_arm_id, COUNT(*) as student_count, AVG(average_percentage) as average_percentage')
            ->groupBy('academic_level_id', 'level_arm_id')
            ->get();

        $gradeCounts = (clone $base)
            ->selectRaw('academic_level_id, level_arm_id, overall_grade_code_snapshot, COUNT(*) as total')
            ->whereNotNull('overall_grade_code_snapshot')
            ->groupBy('academic_level_id', 'level_arm_id', 'overall_grade_code_snapshot')
            ->get()
            ->groupBy(fn ($r) => $r->academic_level_id.'-'.$r->level_arm_id);

        $levelNames = AcademicLevel::query()->whereIn('id', $summaries->pluck('academic_level_id')->unique())->pluck('name', 'id');
        $armNames = LevelArm::query()->whereIn('id', $summaries->pluck('level_arm_id')->filter()->unique())->pluck('name', 'id');

        return $summaries->map(function (StudentResult $row) use ($gradeCounts, $levelNames, $armNames) {
            $key = $row->academic_level_id.'-'.$row->level_arm_id;
            $grades = ($gradeCounts->get($key) ?? collect())
                ->mapWithKeys(fn ($g) => [$g->overall_grade_code_snapshot => (int) $g->total])
                ->all();

            return [
                'academic_level_id' => $row->academic_level_id,
                'level_arm_id' => $row->level_arm_id,
                'level_name' => $levelNames[$row->academic_level_id] ?? null,
                'arm_name' => $row->level_arm_id !== null ? ($armNames[$row->level_arm_id] ?? null) : null,
                'student_count' => (int) $row->student_count,
                'average_percentage' => $row->average_percentage !== null ? round((float) $row->average_percentage, 2) : null,
                'grades' => $grades,
            ];
        })->sortByDesc('average_percentage')->values();
    }

    /**
     * @param  Builder<ResultRun>  $query
     * @param  array{session?:int,period?:int,level?:int,arm?:int,status?:string}  $filters
     */
    private function applyRunFilters(Builder $query, array $filters): void
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
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
    }

    /**
     * @param  Builder<StudentResult>|Builder<StudentSubjectResult>  $query
     * @param  array{session?:int,period?:int,level?:int,arm?:int,run?:int}  $filters
     */
    private function applyResultFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['run'])) {
            $query->where('result_run_id', $filters['run']);
        }
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
    }

    /**
     * A Teacher without `result.manage` sees only the classes (and, when
     * `$subjectColumn` is given, subjects) they hold an active M11
     * assignment for — mirrors `ResultAuthorizer` exactly, generalised via
     * `ReportAuthorizer`. `$armColumn` is accepted for symmetry with the
     * other report classes but not applied here — a Teacher's assignment
     * may be arm-agnostic, and result data is already scoped tightly
     * enough by level (+ subject); narrowing further by arm would hide a
     * Teacher's own arm-agnostic classes from their own report.
     */
    private function scopeToTeacher(Builder $query, User $user, string $levelColumn, string $armColumn, ?string $subjectColumn = null): void
    {
        if ($user->hasPermission(Permission::ResultManage)) {
            return;
        }

        $levelIds = $this->authorizer->levelIdsFor($user);

        if ($levelIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($levelColumn, $levelIds);

        if ($subjectColumn !== null) {
            $subjectIds = $this->authorizer->subjectIdsFor($user);
            $query->whereIn($subjectColumn, $subjectIds === [] ? [0] : $subjectIds);
        }
    }
}
