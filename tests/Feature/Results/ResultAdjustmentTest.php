<?php

namespace Tests\Feature\Results;

use App\Enums\ResultAdjustmentStatus;
use App\Enums\Role;
use App\Models\ResultAdjustment;
use App\Models\ResultRun;
use App\Models\School;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;

class ResultAdjustmentTest extends ResultsTestCase
{
    /** @return array{0: School, 1: ResultRun, 2: StudentSubjectResult, 3: array} */
    private function publishedRun(): array
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->flushSession();

        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();

        return [$school, $run, $subjectResult, $scaffold];
    }

    public function test_a_proposal_has_no_effect_until_applied(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $original = $subjectResult->percentage;
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 90, 'reason' => 'Re-marked script found an addition error.',
        ])->assertRedirect();

        $this->assertSame($original, $subjectResult->fresh()->percentage);
        $adjustment = ResultAdjustment::query()->where('student_subject_result_id', $subjectResult->id)->firstOrFail();
        $this->assertSame(ResultAdjustmentStatus::Pending, $adjustment->status);
    }

    public function test_applying_an_adjustment_updates_the_value_and_re_derives_the_grade(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 20, 'reason' => 'Grading error corrected after re-mark.',
        ]);
        $adjustment = ResultAdjustment::query()->where('student_subject_result_id', $subjectResult->id)->firstOrFail();

        $this->post("/results/runs/{$run->id}/adjustments/{$adjustment->id}/apply")->assertRedirect();

        $fresh = $subjectResult->fresh();
        $this->assertSame('20.00', $fresh->percentage);
        $this->assertSame('F', $fresh->grade_code_snapshot, 'the grade is re-derived from the grading scheme, never typed');
        $this->assertTrue($fresh->is_adjusted);
        $this->assertSame(ResultAdjustmentStatus::Applied, $adjustment->fresh()->status);
    }

    public function test_applying_an_adjustment_recomputes_the_whole_runs_ranking(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2)->values();
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [20, 5]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20, 5]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");

        $last = StudentSubjectResult::query()->where('result_run_id', $run->id)->where('student_id', $students[1]->id)->firstOrFail();
        $this->post("/results/runs/{$run->id}/subject-results/{$last->id}/adjustments", [
            'adjusted_value' => 100, 'reason' => 'External moderation raised this student\'s mark substantially.',
        ]);
        $adjustment = ResultAdjustment::query()->where('student_subject_result_id', $last->id)->firstOrFail();
        $this->post("/results/runs/{$run->id}/adjustments/{$adjustment->id}/apply");

        $overall = StudentResult::query()->where('result_run_id', $run->id)->where('student_id', $students[1]->id)->firstOrFail();
        $this->assertSame(1, $overall->position, 'the previously-last student now outranks the other');
    }

    public function test_an_adjustment_can_be_rejected_with_no_effect(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $original = $subjectResult->percentage;
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 5, 'reason' => 'Proposed in error, should not apply.',
        ]);
        $adjustment = ResultAdjustment::query()->where('student_subject_result_id', $subjectResult->id)->firstOrFail();

        $this->post("/results/runs/{$run->id}/adjustments/{$adjustment->id}/reject")->assertRedirect();

        $this->assertSame(ResultAdjustmentStatus::Rejected, $adjustment->fresh()->status);
        $this->assertSame($original, $subjectResult->fresh()->percentage);
    }

    public function test_a_decided_adjustment_cannot_be_applied_again(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 10, 'reason' => 'First correction to the recorded mark.',
        ]);
        $adjustment = ResultAdjustment::query()->where('student_subject_result_id', $subjectResult->id)->firstOrFail();
        $this->post("/results/runs/{$run->id}/adjustments/{$adjustment->id}/apply");

        $this->from(route('results.runs.show', $run->id))
            ->post("/results/runs/{$run->id}/adjustments/{$adjustment->id}/reject")
            ->assertSessionHas('error');
    }

    public function test_adjustments_are_not_available_before_the_run_requires_them(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");   // still only "compiled" — not approved yet

        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();

        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 10, 'reason' => 'Should be rejected — run not yet approved.',
        ])->assertForbidden();
    }

    public function test_the_adjusted_value_must_differ_from_the_current_percentage(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => $subjectResult->percentage, 'reason' => 'Same value proposed by mistake.',
        ])->assertSessionHasErrors('adjusted_value');
    }

    public function test_a_teacher_cannot_propose_or_decide_adjustments(): void
    {
        [$school, $run, $subjectResult, $scaffold] = $this->publishedRun();
        $this->actingAsTeacherFor($school, $scaffold);

        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 10, 'reason' => 'A teacher should not be able to do this.',
        ])->assertForbidden();
    }

    public function test_adjustments_are_tenant_isolated(): void
    {
        [$school, $run, $subjectResult] = $this->publishedRun();
        $b = $this->newSchool();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/subject-results/{$subjectResult->id}/adjustments", [
            'adjusted_value' => 10, 'reason' => 'Cross-school attempt should 404.',
        ])->assertNotFound();
    }
}
