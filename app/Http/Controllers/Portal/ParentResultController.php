<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Enums\ResultRunStatus;
use App\Http\Controllers\Controller;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Services\Results\ReportCardRenderer;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A child's published academic results (see `docs/parent-portal.md`
 * §"Result visibility"). Only a run whose status is
 * {@see ResultRunStatus::visibleToParents()} (`published` / `locked`) is ever
 * shown — M15 has no dedicated parent-visibility flag, so publication status
 * alone gates this, documented as the safest interpretation.
 *
 * The subject breakdown reuses `App\Services\Results\ReportCardRenderer` —
 * the exact same query the report card itself is built from — so a parent's
 * "Results" page and their "Report card" for the same term can never drift
 * apart.
 */
class ParentResultController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly ReportCardRenderer $renderer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);
        $moduleOn = $this->modules->enabled(Module::Results);

        $results = $moduleOn
            ? StudentResult::query()
                ->where('student_id', $studentModel->id)
                ->whereHas('resultRun', fn ($q) => $q->whereIn('status', $this->visibleStatuses()))
                ->with(['resultRun' => fn ($q) => $q->with(['session', 'period'])])
                ->get()
                ->sortByDesc(fn (StudentResult $sr) => [$sr->resultRun->session?->starts_on, $sr->resultRun->period?->position])
            : collect();

        return view('parent.results.index', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'results' => $results,
            'moduleOn' => $moduleOn,
            'modules' => $this->modules,
        ]);
    }

    public function show(Request $request, int $student, int $run): View
    {
        $this->authorize('portal.parent');

        $studentModel = $this->authorizer->authorizedStudent($request->user(), $student) ?? abort(404);

        $run = ResultRun::query()->with(['session', 'period', 'level', 'arm'])->findOrFail($run);
        abort_unless($run->status->visibleToParents(), 404);

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->where('student_id', $studentModel->id)
            ->firstOrFail();

        $subjectResults = $this->renderer->subjectResultsFor($run, $studentModel->id);

        return view('parent.results.show', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($request->user()),
            'run' => $run,
            'studentResult' => $studentResult,
            'subjectResults' => $subjectResults,
            'modules' => $this->modules,
        ]);
    }

    /** @return list<string> */
    private function visibleStatuses(): array
    {
        return array_map(
            fn (ResultRunStatus $s) => $s->value,
            array_values(array_filter(ResultRunStatus::all(), fn ($s) => $s->visibleToParents())),
        );
    }
}
