<?php

namespace App\Http\Controllers\Assessment;

use App\Enums\AssessmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Assessment\Concerns\ProvidesAcademicOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AssessmentRequest;
use App\Http\Requests\Assessment\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Support\Assessment\AssessmentAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Assessments. `Assessment` / `AssessmentScore` are `BelongsToSchool` models
 * resolved with tenant-scoped `findOrFail`, so another school's id 404s (the
 * lookup runs after the `tenant` middleware). The whole area is behind
 * `module:assessments` **and** `->can('assessment.view' | '.record' | '.manage')`.
 *
 * Creating an assessment snapshots one `AssessmentScore` per currently-eligible
 * student (a single bulk insert). The academic context is then fixed. Lifecycle:
 * draft (structure + scores editable) → published (scores only) → locked
 * (nothing, until an `assessment.manage` holder unlocks it).
 */
class AssessmentController extends Controller
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
            'category' => (int) $request->query('category') ?: null,
            'status' => AssessmentStatus::tryFrom((string) $request->query('status')),
            'date' => $request->query('date') ?: null,
        ];

        $assessments = Assessment::query()
            ->with(['session', 'period', 'level', 'arm', 'subject', 'category'])
            ->withCount([
                'scores',
                'scores as entered_count' => fn ($q) => $q->whereNotNull('score'),
            ])
            ->when($filters['session'], fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($filters['period'], fn ($q, $id) => $q->where('academic_period_id', $id))
            ->when($filters['level'], fn ($q, $id) => $q->where('academic_level_id', $id))
            ->when($filters['arm'], fn ($q, $id) => $q->where('level_arm_id', $id))
            ->when($filters['subject'], fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['category'], fn ($q, $id) => $q->where('assessment_category_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s->value))
            ->when($filters['date'], fn ($q, $d) => $q->whereDate('assessment_date', $d))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('assessments.index', [
            'assessments' => $assessments,
            'filters' => $filters,
            ...$this->academicOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('assessment.record');

        return view('assessments.create', [
            'assessment' => new Assessment(['assessment_date' => now()->toDateString()]),
            ...$this->academicOptions(),
        ]);
    }

    public function store(AssessmentRequest $request): RedirectResponse
    {
        $assessment = DB::transaction(function () use ($request) {
            $assessment = new Assessment($request->validated());
            $assessment->created_by = $request->user()->getKey();
            $assessment->save();

            $this->snapshotRoster($assessment);

            return $assessment;
        });

        return to_route('assessments.show', $assessment)
            ->with('status', __('Assessment created — the class roster has been captured.'));
    }

    public function show(int $assessment): View
    {
        $this->authorize('assessment.view');

        $assessment = Assessment::query()
            ->with(['session', 'period', 'level', 'arm', 'subject', 'category', 'assignment:id,title', 'createdBy:id,name', 'lockedBy:id,name'])
            ->withCount([
                'scores',
                'scores as entered_count' => fn ($q) => $q->whereNotNull('score'),
            ])
            ->findOrFail($assessment);

        return view('assessments.show', [
            'assessment' => $assessment,
            'summary' => $assessment->summary(),
            'canRecord' => $this->authorizer->canRecordFor(
                request()->user(), $assessment->academic_level_id, $assessment->level_arm_id, $assessment->subject_id,
            ),
            'canManage' => request()->user()->hasPermission(Permission::AssessmentManage),
        ]);
    }

    public function edit(int $assessment): View
    {
        $this->authorize('assessment.record');

        $assessment = Assessment::query()->with(['level', 'arm', 'subject'])->findOrFail($assessment);

        abort_unless($assessment->structureEditable(), 403, __('A published assessment cannot be edited. Return it to draft first.'));
        $this->assertCanRecord($assessment);

        return view('assessments.edit', [
            'assessment' => $assessment,
            'categories' => $this->academicOptions()['categories'],
        ]);
    }

    public function update(UpdateAssessmentRequest $request, int $assessment): RedirectResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        $assessment->update($request->safe()->only(['assessment_category_id', 'title', 'max_score', 'instructions']));

        return to_route('assessments.show', $assessment)->with('status', __('Assessment updated.'));
    }

    public function destroy(Request $request, int $assessment): RedirectResponse
    {
        $this->authorize('assessment.record');

        $assessment = Assessment::query()->findOrFail($assessment);
        $this->assertCanRecord($assessment);

        if ($assessment->isLocked()) {
            return to_route('assessments.show', $assessment)->with('error', __('Unlock the assessment before deleting it.'));
        }

        if ($assessment->scores()->whereNotNull('score')->exists()) {
            return to_route('assessments.show', $assessment)->with('error', __('This assessment has recorded scores and cannot be deleted.'));
        }

        $assessment->delete();

        return to_route('assessments.index')->with('status', __('Assessment deleted.'));
    }

    public function publish(Request $request, int $assessment): RedirectResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        $this->assertCanRecord($assessment);

        if (! $assessment->isDraft()) {
            return back()->with('error', __('Only a draft assessment can be published.'));
        }

        $assessment->publish();

        return to_route('assessments.show', $assessment)->with('status', __('Assessment published.'));
    }

    public function unpublish(Request $request, int $assessment): RedirectResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        $this->assertCanRecord($assessment);

        if (! $assessment->isPublished()) {
            return back()->with('error', __('Only a published assessment can be returned to draft.'));
        }

        $assessment->unpublish();

        return to_route('assessments.show', $assessment)->with('status', __('Assessment returned to draft.'));
    }

    public function lock(Request $request, int $assessment): RedirectResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        $this->assertCanRecord($assessment);

        if ($assessment->isLocked()) {
            return back()->with('error', __('That assessment is already locked.'));
        }

        $assessment->lock($request->user());

        return to_route('assessments.show', $assessment)->with('status', __('Assessment locked.'));
    }

    public function unlock(int $assessment): RedirectResponse
    {
        $this->authorize('assessment.manage');

        $assessment = Assessment::query()->findOrFail($assessment);

        if (! $assessment->isLocked()) {
            return to_route('assessments.show', $assessment)->with('error', __('That assessment is not locked.'));
        }

        $assessment->unlock();

        return to_route('assessments.show', $assessment)->with('status', __('Assessment unlocked for correction.'));
    }

    private function assertCanRecord(Assessment $assessment): void
    {
        abort_unless(
            $this->authorizer->canRecordFor(
                request()->user(), $assessment->academic_level_id, $assessment->level_arm_id, $assessment->subject_id,
            ),
            403,
        );
    }

    /** One `AssessmentScore` (null) per currently-eligible student, in a single insert. */
    private function snapshotRoster(Assessment $assessment): void
    {
        $rows = $assessment->eligibleStudents()->pluck('id')->map(fn ($studentId) => [
            'school_id' => $this->tenant->idOrFail(),
            'assessment_id' => $assessment->id,
            'student_id' => $studentId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        if ($rows !== []) {
            AssessmentScore::query()->insert($rows);
        }
    }
}
