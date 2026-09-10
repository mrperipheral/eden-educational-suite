<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\SubmissionRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bulk completion tracking for one assignment. Behind `module:assessments` +
 * `->can('assessment.record')` and the {@see AssessmentAuthorizer} class/subject
 * check. Loads the roster + existing rows in a fixed number of queries and
 * writes only the changed rows.
 */
class AssignmentSubmissionController extends Controller
{
    public function __construct(private readonly AssessmentAuthorizer $authorizer) {}

    public function edit(int $assignment): View
    {
        $this->authorize('assessment.record');

        $assignment = Assignment::query()
            ->with(['level', 'arm', 'subject', 'session', 'period'])
            ->findOrFail($assignment);

        $this->assertCanRecord($assignment);

        $submissions = $assignment->submissions()
            ->with(['student:id,first_name,middle_name,last_name,preferred_name,admission_number,status'])
            ->get()
            ->sortBy(fn (AssignmentSubmission $s) => [$s->student?->last_name, $s->student?->first_name])
            ->values();

        return view('assignments.submissions', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'summary' => $assignment->summary(),
        ]);
    }

    public function update(SubmissionRequest $request, int $assignment): RedirectResponse
    {
        $assignment = Assignment::query()->findOrFail($assignment);
        $userId = $request->user()->getKey();
        $existing = $assignment->submissions()->get()->keyBy('student_id');

        DB::transaction(function () use ($request, $existing, $userId) {
            foreach ($request->submissions() as $studentId => $data) {
                $submission = $existing->get($studentId);

                if ($submission === null) {
                    continue;
                }

                $submission->status = $data['status'];
                $submission->submitted_on = $data['submitted_on'];
                $submission->remark = $data['remark'];

                if ($submission->isDirty(['status', 'submitted_on', 'remark'])) {
                    $submission->recorded_by = $userId;
                    $submission->recorded_at = now();
                    $submission->save();
                }
            }
        });

        return to_route('assessments.assignments.submissions.edit', $assignment)->with('status', __('Completion saved.'));
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
}
