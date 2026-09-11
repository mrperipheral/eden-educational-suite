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
 * The signed-in student's own published report cards (see
 * `docs/student-portal.md` §"Report cards"). `show()` renders through the
 * **same** `App\Services\Results\ReportCardRenderer` the school/staff side
 * and the Parent Portal (M16) both use — there is no second generator. Only
 * `published` / `locked` runs are ever reachable.
 */
class StudentReportCardController extends Controller
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
                ->whereHas('resultRun', fn ($q) => $q->whereIn('status', [ResultRunStatus::Published->value, ResultRunStatus::Locked->value]))
                ->with(['resultRun' => fn ($q) => $q->with(['session', 'period'])])
                ->get()
                ->sortByDesc(fn (StudentResult $sr) => [$sr->resultRun->session?->starts_on, $sr->resultRun->period?->position])
            : collect();

        return view('student.report-cards.index', [
            'student' => $student,
            'results' => $results,
            'moduleOn' => $moduleOn,
        ]);
    }

    public function show(Request $request, int $run): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user()) ?? abort(404);

        $run = ResultRun::query()->findOrFail($run);
        abort_unless($run->status->visibleToParents(), 404);

        $studentResult = StudentResult::query()
            ->where('result_run_id', $run->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        return $this->renderer->render($run, $studentResult);
    }
}
