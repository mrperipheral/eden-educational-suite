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
 * A child's published report cards (see `docs/parent-portal.md` §"Report
 * card reuse" / §"Report card visibility"). `show()` renders through the
 * **same** `App\Services\Results\ReportCardRenderer` the school/staff side
 * uses (`App\Http\Controllers\Results\ReportCardController`) — there is no
 * second report-card generator. Only `published` / `locked` runs are ever
 * reachable; a parent can never guess a run id into an in-progress report.
 */
class ParentReportCardController extends Controller
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
                ->whereHas('resultRun', fn ($q) => $q->whereIn('status', [ResultRunStatus::Published->value, ResultRunStatus::Locked->value]))
                ->with(['resultRun' => fn ($q) => $q->with(['session', 'period'])])
                ->get()
                ->sortByDesc(fn (StudentResult $sr) => [$sr->resultRun->session?->starts_on, $sr->resultRun->period?->position])
            : collect();

        return view('parent.report-cards.index', [
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

        $run = ResultRun::query()->findOrFail($run);
        abort_unless($run->status->visibleToParents(), 404);

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->where('student_id', $studentModel->id)
            ->firstOrFail();

        return $this->renderer->render($run, $studentResult);
    }
}
