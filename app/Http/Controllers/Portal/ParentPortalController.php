<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Enums\ResultRunStatus;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\StudentResult;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Parent Portal's landing page — "My Children" (see
 * `docs/parent-portal.md`). Gated `module:parent-portal` **and**
 * `->can('portal.parent')`. Every student shown is resolved through
 * `App\Support\Portal\ParentPortalAuthorizer`, never a raw query the view
 * happens to filter — an unlinked parent or a guardian with no linked
 * students sees a safe empty state, never an error.
 */
class ParentPortalController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.parent');

        $guardian = $this->authorizer->guardianFor($request->user());

        $students = $guardian
            ? $guardian->students()->ordered()
                ->with(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])])
                ->get()
            : collect();

        return view('parent.dashboard', [
            'guardian' => $guardian,
            'students' => $students,
            // M29.5 — a compact fees/results summary per child, for the
            // dashboard cards only. The guardian's own children list is
            // always small (not proportional to school size), so one query
            // per child here stays bounded regardless of how large the
            // school itself grows.
            'outstandingByStudent' => $this->modules->enabled(Module::Fees)
                ? $this->outstandingByStudent($students)
                : null,
            'latestResultByStudent' => $this->modules->enabled(Module::Results)
                ? $this->latestResultByStudent($students)
                : null,
        ]);
    }

    /**
     * One bulk query (plus its eager loads) for every linked child at once —
     * the query count must not grow with how many children a guardian has
     * (see `ParentPortalStructureTest::test_the_dashboard_query_count_stays_bounded_as_the_parents_own_children_grow`).
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, string> student id => outstanding balance
     */
    private function outstandingByStudent(Collection $students): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        return StudentFeeCharge::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->with('allocations.payment:id,voided_at')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $charges) => $charges->reduce(
                fn (string $carry, StudentFeeCharge $c) => bcadd($carry, $c->outstandingBalance(), 2),
                '0.00',
            ))
            ->all();
    }

    /**
     * Same bulk-query guarantee as {@see outstandingByStudent()} — one query
     * (plus its eager loads) for every linked child at once.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, StudentResult> student id => their most recent visible result
     */
    private function latestResultByStudent(Collection $students): array
    {
        if ($students->isEmpty()) {
            return [];
        }

        $visible = array_map(
            fn (ResultRunStatus $s) => $s->value,
            array_values(array_filter(ResultRunStatus::all(), fn ($s) => $s->visibleToParents())),
        );

        return StudentResult::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereHas('resultRun', fn ($q) => $q->whereIn('status', $visible))
            ->with(['resultRun' => fn ($q) => $q->with(['session', 'period'])])
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $results) => $results
                ->sortByDesc(fn (StudentResult $sr) => [$sr->resultRun->session?->starts_on, $sr->resultRun->period?->position])
                ->first())
            ->all();
    }
}
