<?php

namespace App\Http\Controllers\Assessment;

use App\Enums\AssignmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Assessment\Concerns\ProvidesAcademicOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AssignmentRequest;
use App\Http\Requests\Assessment\UpdateAssignmentRequest;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Support\Assessment\AssessmentAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Class assignments. `Assignment` / `AssignmentSubmission` are `BelongsToSchool`
 * models resolved with tenant-scoped `findOrFail`. Behind `module:assessments`
 * **and** `->can('assessment.view' | '.record')`.
 *
 * Creating an assignment captures a `pending` completion row per currently-
 * eligible student. Lifecycle: draft → published → closed. An assignment holds
 * no scores — grading is a separate {@see Assessment}.
 */
class AssignmentController extends Controller
{
    use ProvidesAcademicOptions;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AssessmentAuthorizer $authorizer,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('assessment.view');

        $filters = [
            'session' => (int) $request->query('session') ?: null,
            'period' => (int) $request->query('period') ?: null,
            'level' => (int) $request->query('level') ?: null,
            'arm' => (int) $request->query('arm') ?: null,
            'subject' => (int) $request->query('subject') ?: null,
            'status' => AssignmentStatus::tryFrom((string) $request->query('status')),
        ];

        $assignments = Assignment::query()
            ->with(['session', 'period', 'level', 'arm', 'subject', 'teacher:id,first_name,last_name,preferred_name'])
            ->withCount([
                'submissions',
                'submissions as turned_in_count' => fn ($q) => $q->whereIn('status', ['submitted', 'late']),
            ])
            ->when($filters['session'], fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($filters['period'], fn ($q, $id) => $q->where('academic_period_id', $id))
            ->when($filters['level'], fn ($q, $id) => $q->where('academic_level_id', $id))
            ->when($filters['arm'], fn ($q, $id) => $q->where('level_arm_id', $id))
            ->when($filters['subject'], fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s->value))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('assignments.index', [
            'assignments' => $assignments,
            'filters' => $filters,
            ...$this->academicOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('assessment.record');

        return view('assignments.create', [
            'assignment' => new Assignment([
                'assigned_on' => now()->toDateString(),
                'due_on' => now()->addWeek()->toDateString(),
            ]),
            ...$this->academicOptions(),
        ]);
    }

    public function store(AssignmentRequest $request): RedirectResponse
    {
        $assignment = DB::transaction(function () use ($request) {
            $assignment = new Assignment($request->validated());
            $assignment->created_by = $request->user()->getKey();
            $assignment->teacher_id = app(AssessmentAuthorizer::class)->teacherIdFor($request->user());
            $assignment->save();

            $this->snapshotRoster($assignment);

            return $assignment;
        });

        return to_route('assessments.assignments.show', $assignment)
            ->with('status', __('Assignment created — the class roster has been captured.'));
    }

    public function show(int $assignment): View
    {
        $this->authorize('assessment.view');

        $assignment = Assignment::query()
            ->with(['session', 'period', 'level', 'arm', 'subject', 'teacher', 'createdBy:id,name'])
            ->withCount([
                'submissions',
                'submissions as turned_in_count' => fn ($q) => $q->whereIn('status', ['submitted', 'late']),
            ])
            ->findOrFail($assignment);

        return view('assignments.show', [
            'assignment' => $assignment,
            'summary' => $assignment->summary(),
            'canRecord' => $this->authorizer->canRecordFor(
                request()->user(), $assignment->academic_level_id, $assignment->level_arm_id, $assignment->subject_id,
            ),
            'canManage' => request()->user()->hasPermission(Permission::AssessmentManage),
        ]);
    }

    public function edit(int $assignment): View
    {
        $this->authorize('assessment.record');

        $assignment = Assignment::query()->with(['level', 'arm', 'subject'])->findOrFail($assignment);

        abort_unless($assignment->structureEditable(), 403, __('A published assignment cannot be edited. Return it to draft first.'));
        $this->assertCanRecord($assignment);

        return view('assignments.edit', ['assignment' => $assignment]);
    }

    public function update(UpdateAssignmentRequest $request, int $assignment): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($assignment);
        $assignment->update($request->safe()->only(['title', 'instructions', 'assigned_on', 'due_on', 'max_score']));

        return to_route('assessments.assignments.show', $assignment)->with('status', __('Assignment updated.'));
    }

    public function destroy(Request $request, int $assignment): RedirectResponse
    {
        $this->authorize('assessment.record');

        $assignment = Assignment::query()->findOrFail($assignment);
        $this->assertCanRecord($assignment);

        if ($assignment->submissions()->where('status', '!=', 'pending')->exists()) {
            return to_route('assessments.assignments.show', $assignment)
                ->with('error', __('This assignment has recorded submissions and cannot be deleted.'));
        }

        $assignment->delete();

        return to_route('assessments.assignments.index')->with('status', __('Assignment deleted.'));
    }

    public function publish(Request $request, int $assignment): RedirectResponse
    {
        return $this->transition($assignment, fn (Assignment $a) => $a->isDraft(), fn (Assignment $a) => $a->publish(),
            __('Only a draft assignment can be published.'), __('Assignment published.'));
    }

    public function unpublish(Request $request, int $assignment): RedirectResponse
    {
        return $this->transition($assignment, fn (Assignment $a) => $a->isPublished(), fn (Assignment $a) => $a->unpublish(),
            __('Only a published assignment can be returned to draft.'), __('Assignment returned to draft.'));
    }

    public function close(Request $request, int $assignment): RedirectResponse
    {
        return $this->transition($assignment, fn (Assignment $a) => $a->isPublished(), fn (Assignment $a) => $a->close(),
            __('Only a published assignment can be closed.'), __('Assignment closed.'));
    }

    public function reopen(Request $request, int $assignment): RedirectResponse
    {
        return $this->transition($assignment, fn (Assignment $a) => $a->isClosed(), fn (Assignment $a) => $a->reopen(),
            __('That assignment is not closed.'), __('Assignment reopened.'));
    }

    private function transition(int $assignmentId, callable $guard, callable $apply, string $failure, string $success): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($assignmentId);
        $this->assertCanRecord($assignment);

        if (! $guard($assignment)) {
            return back()->with('error', $failure);
        }

        $apply($assignment);

        return to_route('assessments.assignments.show', $assignment)->with('status', $success);
    }

    private function assertCanRecord(Assignment $assignment): void
    {
        abort_unless(
            $this->authorizer->canRecordFor(
                request()->user(), $assignment->academic_level_id, $assignment->level_arm_id, $assignment->subject_id,
            ),
            403,
        );
    }

    private function snapshotRoster(Assignment $assignment): void
    {
        $rows = $assignment->eligibleStudents()->pluck('id')->map(fn ($studentId) => [
            'school_id' => $this->tenant->idOrFail(),
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            AssignmentSubmission::query()->insert($rows);
        }
    }
}
