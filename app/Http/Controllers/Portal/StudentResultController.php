<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Enums\ResultRunStatus;
use App\Http\Controllers\Controller;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Services\Results\ReportCardRenderer;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own published academic results (see
 * `docs/student-portal.md` §"Result visibility"). Only a run whose status is
 * {@see ResultRunStatus::visibleToParents()} (`published` / `locked` —
 * shared with the Parent Portal, M16) is ever shown. Reuses
 * `App\Services\Results\ReportCardRenderer::subjectResultsFor()` — the exact
 * query the report card's own table is built from.
 */
class StudentResultController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly ReportCardRenderer $renderer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $moduleOn = $this->modules->enabled(Module::Results);

        $results = ($student && $moduleOn)
            ? StudentResult::query()
                ->where('student_id', $student->id)
                ->whereHas('resultRun', fn ($q) => $q->whereIn('status', $this->visibleStatuses()))
                ->with(['resultRun' => fn ($q) => $q->with(['session', 'period'])])
                ->get()
                ->sortByDesc(fn (StudentResult $sr) => [$sr->resultRun->session?->starts_on, $sr->resultRun->period?->position])
            : collect();

        return view('student.results.index', [
            'student' => $student,
            'results' => $results,
            'moduleOn' => $moduleOn,
        ]);
    }

    public function show(Request $request, int $run): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);

        $run = ResultRun::query()->with(['session', 'period', 'level', 'arm'])->findOrFail($run);
        abort_unless($run->status->visibleToParents(), 404);

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $subjectResults = $this->renderer->subjectResultsFor($run, $student->id);

        return view('student.results.show', [
            'student' => $student,
            'run' => $run,
            'studentResult' => $studentResult,
            'subjectResults' => $subjectResults,
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
