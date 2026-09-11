<?php

namespace App\Http\Controllers\Results;

use App\Enums\Permission;
use App\Enums\ResultRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\ResultCommentRequest;
use App\Http\Requests\Results\ResultRunRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\GradingScheme;
use App\Models\ReportCardConfiguration;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use App\Services\Results\ResultCompiler;
use App\Support\Results\ResultAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Result runs — one class's compiled results for one term. `ResultRun` /
 * `StudentResult` / `StudentSubjectResult` are `BelongsToSchool` models
 * resolved with tenant-scoped `findOrFail`. The whole area is behind
 * `module:results` **and** `->can('result.view' | '.manage' | '.publish' |
 * '.enter')`.
 *
 * Compilation is `App\Services\Results\ResultCompiler`'s job — this controller
 * only orchestrates the lifecycle and surfaces a blocked compile's issues.
 */
class ResultRunController extends Controller
{
    public function __construct(private readonly ResultCompiler $compiler) {}

    public function index(Request $request): View
    {
        $this->authorize('result.view');

        $filters = [
            'session' => (int) $request->query('session') ?: null,
            'level' => (int) $request->query('level') ?: null,
            'arm' => (int) $request->query('arm') ?: null,
            'status' => ResultRunStatus::tryFrom((string) $request->query('status')),
        ];

        $runs = ResultRun::query()
            ->with(['session', 'period', 'level', 'arm'])
            ->withCount('studentResults')
            ->when($filters['session'], fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($filters['level'], fn ($q, $id) => $q->where('academic_level_id', $id))
            ->when($filters['arm'], fn ($q, $id) => $q->where('level_arm_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s->value))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('results.runs.index', [
            'runs' => $runs,
            'filters' => $filters,
            ...$this->options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('result.manage');

        return view('results.runs.create', $this->options());
    }

    public function store(ResultRunRequest $request): RedirectResponse
    {
        $run = ResultRun::create($request->validated());

        return to_route('results.runs.show', $run)->with('status', __('Result run created — compile it once every subject is locked and scored.'));
    }

    public function show(int $run): View
    {
        $this->authorize('result.view');

        $run = ResultRun::query()
            ->with(['session', 'period', 'level', 'arm', 'gradingScheme', 'weightingScheme', 'compiledBy:id,name', 'reviewedBy:id,name', 'approvedBy:id,name', 'publishedBy:id,name', 'lockedBy:id,name'])
            ->findOrFail($run);

        $studentResults = $run->studentResults()
            ->with(['student:id,first_name,middle_name,last_name,preferred_name,admission_number,status'])
            ->ordered()
            ->get();

        $subjectResultsByStudent = $run->subjectResults()
            ->with(['subject:id,name', 'components' => fn ($q) => $q->ordered(), 'adjustments' => fn ($q) => $q->ordered()->with(['requestedBy:id,name', 'decidedBy:id,name'])])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->sortBy(fn ($sr) => $sr->subject?->name));

        return view('results.runs.show', [
            'run' => $run,
            'studentResults' => $studentResults,
            'subjectResultsByStudent' => $subjectResultsByStudent,
            'canManage' => request()->user()->hasPermission(Permission::ResultManage),
            'canPublish' => request()->user()->hasPermission(Permission::ResultPublish),
            'canAdjust' => request()->user()->hasPermission(Permission::ResultAdjust),
            'canComment' => app(ResultAuthorizer::class)->canCommentOnRun(request()->user(), $run),
        ]);
    }

    public function destroy(int $run): RedirectResponse
    {
        $this->authorize('result.manage');

        $run = ResultRun::query()->findOrFail($run);

        if ($run->studentResults()->exists()) {
            return to_route('results.runs.show', $run)->with('error', __('This run has compiled results and cannot be deleted. Compiling again replaces them safely.'));
        }

        $run->delete();

        return to_route('results.runs.index')->with('status', __('Result run deleted.'));
    }

    public function compile(Request $request, int $run): RedirectResponse
    {
        $this->authorize('result.manage');

        $run = ResultRun::query()->findOrFail($run);

        if (! $run->recompilable()) {
            return to_route('results.runs.show', $run)->with('error', __('This run can no longer be recompiled — reviewed results require the adjustment workflow.'));
        }

        $outcome = $this->compiler->compile($run, $request->user());

        if (! $outcome->success) {
            return to_route('results.runs.show', $run)
                ->withErrors(['compilation' => array_map(fn ($issue) => $issue->message(), $outcome->issues)])
                ->with('error', __('Compilation was blocked — some scores are missing.'));
        }

        return to_route('results.runs.show', $run)->with('status', __('Results compiled for :n students.', ['n' => $outcome->studentCount]));
    }

    public function review(Request $request, int $run): RedirectResponse
    {
        $run = ResultRun::query()->findOrFail($run);
        $this->authorize('result.manage');

        if (! $run->isCompiled()) {
            return to_route('results.runs.show', $run)->with('error', __('Only a compiled run can be marked reviewed.'));
        }

        $run->review($request->user());

        return to_route('results.runs.show', $run)->with('status', __('Marked reviewed.'));
    }

    public function approve(Request $request, int $run): RedirectResponse
    {
        $run = ResultRun::query()->findOrFail($run);
        $this->authorize('result.publish');

        if (! $run->isReviewed()) {
            return to_route('results.runs.show', $run)->with('error', __('Only a reviewed run can be approved.'));
        }

        $run->approve($request->user());

        return to_route('results.runs.show', $run)->with('status', __('Approved. Numbers are now frozen — use an adjustment to correct anything from here.'));
    }

    public function publish(Request $request, int $run): RedirectResponse
    {
        $run = ResultRun::query()->findOrFail($run);
        $this->authorize('result.publish');

        if (! $run->isApproved()) {
            return to_route('results.runs.show', $run)->with('error', __('Only an approved run can be published.'));
        }

        $run->publish($request->user());
        ReportCardConfiguration::snapshotForRun($run);

        return to_route('results.runs.show', $run)->with('status', __('Published — report cards are now available.'));
    }

    public function lock(Request $request, int $run): RedirectResponse
    {
        $run = ResultRun::query()->findOrFail($run);
        $this->authorize('result.publish');

        if (! $run->isPublished()) {
            return to_route('results.runs.show', $run)->with('error', __('Only a published run can be locked.'));
        }

        $run->lock($request->user());

        return to_route('results.runs.show', $run)->with('status', __('Locked.'));
    }

    public function updateComment(ResultCommentRequest $request, int $run, int $student_result): RedirectResponse
    {
        $studentResult = $request->studentResult();
        $studentResult->update($request->payload());

        return to_route('results.runs.show', $run)->with('status', __('Comment saved.'));
    }

    /**
     * @return array{sessions: Collection, levels: Collection, gradingSchemes: Collection, weightingSchemes: Collection}
     */
    private function options(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            'gradingSchemes' => GradingScheme::query()->active()->ordered()->get(),
            'weightingSchemes' => ResultWeightingScheme::query()->active()->with('items')->ordered()->get(),
        ];
    }
}
