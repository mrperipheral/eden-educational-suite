<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\ScoreRequest;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Support\Assessment\AssessmentAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bulk score entry for one assessment. Behind `module:assessments` +
 * `->can('assessment.record')`; the finer "may record for *this* class + subject"
 * rule is {@see AssessmentAuthorizer}. `{assessment}` is resolved tenant-scoped.
 *
 * The entry screen loads the roster and its existing scores in a fixed number of
 * queries; the save writes only the rows whose score or comment changed.
 */
class AssessmentScoreController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AssessmentAuthorizer $authorizer,
    ) {}

    public function edit(int $assessment): View
    {
        $this->authorize('assessment.record');

        $assessment = Assessment::query()
            ->with(['level', 'arm', 'subject', 'category', 'session', 'period'])
            ->findOrFail($assessment);

        abort_unless($assessment->acceptsScores(), 403, __('A locked assessment cannot be edited. Ask a manager to unlock it.'));
        $this->assertCanRecord($assessment);

        $scores = $assessment->scores()
            ->with(['student:id,first_name,middle_name,last_name,preferred_name,admission_number,status'])
            ->get()
            ->sortBy(fn (AssessmentScore $s) => [$s->student?->last_name, $s->student?->first_name])
            ->values();

        return view('assessments.scores', [
            'assessment' => $assessment,
            'scores' => $scores,
            'summary' => $assessment->summary(),
        ]);
    }

    public function update(ScoreRequest $request, int $assessment): RedirectResponse
    {
        $assessment = Assessment::query()->findOrFail($assessment);
        $userId = $request->user()->getKey();
        $existing = $assessment->scores()->get()->keyBy('student_id');

        DB::transaction(function () use ($request, $existing, $userId) {
            foreach ($request->scores() as $studentId => $data) {
                $score = $existing->get($studentId);

                if ($score === null) {
                    continue;
                }

                $score->score = $data['score'];
                $score->comment = $data['comment'];

                if ($score->isDirty(['score', 'comment'])) {
                    $score->recorded_by = $userId;
                    $score->recorded_at = now();
                    $score->save();
                }
            }
        });

        return to_route('assessments.scores.edit', $assessment)->with('status', __('Scores saved.'));
    }

    /**
     * Reconcile a **draft** assessment's roster with current enrolment — add a
     * null-score row for any newly-eligible student. Never removes a row.
     */
    public function sync(Request $request, int $assessment): RedirectResponse
    {
        $this->authorize('assessment.record');

        $assessment = Assessment::query()->findOrFail($assessment);
        $this->assertCanRecord($assessment);

        if (! $assessment->isDraft()) {
            return to_route('assessments.scores.edit', $assessment)
                ->with('error', __('The roster is frozen once the assessment is published.'));
        }

        $have = $assessment->scores()->pluck('student_id')->all();
        $rows = $assessment->eligibleStudents()->pluck('id')
            ->reject(fn ($id) => in_array($id, $have, true))
            ->map(fn ($studentId) => [
                'school_id' => $this->tenant->idOrFail(),
                'assessment_id' => $assessment->id,
                'student_id' => $studentId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        if ($rows !== []) {
            AssessmentScore::query()->insert($rows);
        }

        return to_route('assessments.scores.edit', $assessment)->with('status', trans_choice(
            '{0}No new students to add.|{1}Added :count student to the roster.|[2,*]Added :count students to the roster.',
            count($rows),
            ['count' => count($rows)],
        ));
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
}
