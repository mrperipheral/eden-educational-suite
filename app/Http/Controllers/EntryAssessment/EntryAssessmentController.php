<?php

namespace App\Http\Controllers\EntryAssessment;

use App\Enums\EntryAssessmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\EntryAssessment\EntryAssessmentRequest;
use App\Models\AcademicLevel;
use App\Models\EntryAssessment;
use App\Models\Student;
use App\Models\Subject;
use App\Support\EntryAssessment\EntryAssessmentAuthorizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entry / Placement Assessment records (M25, `docs/entry-placement-
 * assessment.md`). Gated `placement.view` (read) /
 * `.record`/`.manage` (write), behind `module:entry-assessment`. A Teacher
 * holding `.record` without `.manage` may only create/edit a record for a
 * subject/level (+ arm, if set) they hold an active M11 `TeacherAssignment`
 * for — re-checked server-side via
 * `App\Support\EntryAssessment\EntryAssessmentAuthorizer::canManageFor()`,
 * never trusted from the request alone. *Viewing* the list (including
 * export) is not further scoped — the same choice M23/M24 make for their
 * own staff-facing lists.
 *
 * This records the assessment only — no placement recommendation, no
 * automatic enrolment, no CBT engine of its own.
 */
class EntryAssessmentController extends Controller
{
    public function __construct(private readonly EntryAssessmentAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorize('placement.view');

        return view('entry-assessments.index', [
            'assessments' => $this->filteredQuery($request)->paginate(15)->withQueryString(),
            ...$this->filterOptions(),
            'filters' => $request->only(['q', 'level', 'arm', 'subject', 'status']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('placement.view');

        $filename = 'entry-placement-assessments-'.now()->format('Y-m-d-His').'.csv';

        $columns = ['Candidate name', 'Admission reference', 'Assessment date', 'Level', 'Arm', 'Subject', 'Score', 'Max score', 'Percentage', 'Result', 'Status', 'Assessor', 'Notes'];

        $query = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            // chunk() (not cursor()) — cursor() skips eager-loading relations
            // entirely, which would N+1 level/arm/subject/assessor per row.
            // Chunking keeps memory bounded to one page at a time while each
            // page's relations are still loaded in bulk.
            $query->chunk(200, function (Collection $chunk) use ($handle) {
                foreach ($chunk as $assessment) {
                    /** @var EntryAssessment $assessment */
                    fputcsv($handle, [
                        $assessment->candidate_name,
                        $assessment->admission_reference,
                        $assessment->assessed_on->toDateString(),
                        $assessment->level?->name,
                        $assessment->arm?->name,
                        $assessment->subject?->name,
                        $assessment->score,
                        $assessment->max_score,
                        $assessment->percentage(),
                        $assessment->result,
                        $assessment->status->label(),
                        $assessment->assessor?->name,
                        $assessment->notes,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request): View
    {
        $this->authorize('placement.record');

        return view('entry-assessments.create', [
            ...$this->classOptions(),
            'unrestricted' => $request->user()->hasPermission(Permission::EntryAssessmentManage),
            'assignments' => $this->authorizer->assignmentsFor($request->user()),
        ]);
    }

    public function store(EntryAssessmentRequest $request): RedirectResponse
    {
        $context = $request->context();

        $allowed = $this->authorizer->canManageFor(
            $request->user(),
            $context['academic_level_id'],
            $context['level_arm_id'],
            $context['subject_id'],
        );
        abort_unless($allowed, 403);

        $assessment = new EntryAssessment($context);
        $assessment->assessor_id = $request->user()->id;
        $assessment->save();

        return to_route('entry-assessments.index')->with('status', __('Entry / Placement Assessment record created.'));
    }

    public function show(int $entryAssessment): View
    {
        $this->authorize('placement.view');

        $assessment = EntryAssessment::query()
            ->with(['student:id,first_name,last_name,admission_number', 'level:id,name', 'arm:id,name', 'subject:id,name', 'assessor:id,name'])
            ->findOrFail($entryAssessment);

        return view('entry-assessments.show', ['assessment' => $assessment]);
    }

    public function edit(Request $request, int $entryAssessment): View
    {
        $assessment = EntryAssessment::query()->findOrFail($entryAssessment);
        abort_unless($this->authorizer->canManage($request->user(), $assessment), 403);

        return view('entry-assessments.edit', [
            'assessment' => $assessment,
            ...$this->classOptions(),
        ]);
    }

    public function update(EntryAssessmentRequest $request, int $entryAssessment): RedirectResponse
    {
        $assessment = EntryAssessment::query()->findOrFail($entryAssessment);
        abort_unless($this->authorizer->canManage($request->user(), $assessment), 403);

        $context = $request->context();
        $allowed = $this->authorizer->canManageFor(
            $request->user(),
            $context['academic_level_id'],
            $context['level_arm_id'],
            $context['subject_id'],
        );
        abort_unless($allowed, 403);

        $assessment->fill($context);
        $assessment->save();

        return to_route('entry-assessments.index')->with('status', __('Entry / Placement Assessment record updated.'));
    }

    public function archive(Request $request, int $entryAssessment): RedirectResponse
    {
        $assessment = EntryAssessment::query()->findOrFail($entryAssessment);
        abort_unless($this->authorizer->canManage($request->user(), $assessment), 403);

        $assessment->archive();

        return back()->with('status', __('Record archived.'));
    }

    public function restore(Request $request, int $entryAssessment): RedirectResponse
    {
        $assessment = EntryAssessment::query()->findOrFail($entryAssessment);
        abort_unless($this->authorizer->canManage($request->user(), $assessment), 403);

        $assessment->restore();

        return back()->with('status', __('Record restored.'));
    }

    /**
     * @return Builder<EntryAssessment>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = EntryAssessment::query()
            ->with(['student:id,first_name,last_name,admission_number', 'level:id,name', 'arm:id,name', 'subject:id,name', 'assessor:id,name'])
            ->ordered();

        if ($request->filled('q')) {
            $query->search($request->string('q')->trim()->value());
        }
        if ($request->filled('level')) {
            $query->where('academic_level_id', $request->integer('level'));
        }
        if ($request->filled('arm')) {
            $query->where('level_arm_id', $request->integer('arm'));
        }
        if ($request->filled('subject')) {
            $query->where('subject_id', $request->integer('subject'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query;
    }

    /**
     * @return array{subjects: Collection, levels: Collection, statuses: list<EntryAssessmentStatus>}
     */
    private function filterOptions(): array
    {
        return [
            ...$this->classOptions(),
            'statuses' => EntryAssessmentStatus::all(),
        ];
    }

    /**
     * @return array{subjects: Collection, levels: Collection, students: Collection}
     */
    private function classOptions(): array
    {
        return [
            'subjects' => Subject::query()->ordered()->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered(), 'subjects' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
            'students' => Student::query()->ordered()->get(['id', 'first_name', 'last_name', 'admission_number']),
        ];
    }
}
