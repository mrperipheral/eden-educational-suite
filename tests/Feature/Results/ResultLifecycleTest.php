<?php

namespace Tests\Feature\Results;

use App\Enums\ResultRunStatus;
use App\Enums\Role;

class ResultLifecycleTest extends ResultsTestCase
{
    private function compiledRun(): array
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10, 15]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15, 18]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->flushSession();

        return [$school, $run];
    }

    public function test_the_full_lifecycle_runs_draft_to_locked(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->assertSame(ResultRunStatus::Compiled, $run->fresh()->status);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/review")->assertRedirect();
        $this->assertSame(ResultRunStatus::Reviewed, $run->fresh()->status);

        $this->post("/results/runs/{$run->id}/approve")->assertRedirect();
        $fresh = $run->fresh();
        $this->assertSame(ResultRunStatus::Approved, $fresh->status);
        $this->assertNotNull($fresh->approved_at);

        $this->post("/results/runs/{$run->id}/publish")->assertRedirect();
        $fresh = $run->fresh();
        $this->assertSame(ResultRunStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->published_at);
        $this->assertDatabaseHas('report_card_configurations', ['result_run_id' => $run->id]);

        $this->post("/results/runs/{$run->id}/lock")->assertRedirect();
        $fresh = $run->fresh();
        $this->assertSame(ResultRunStatus::Locked, $fresh->status);
        $this->assertNotNull($fresh->locked_at);
    }

    public function test_transitions_out_of_order_are_rejected(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/approve")
            ->assertSessionHas('error');
        $this->assertSame(ResultRunStatus::Compiled, $run->fresh()->status);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/publish")
            ->assertSessionHas('error');
        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/lock")
            ->assertSessionHas('error');
    }

    public function test_a_teacher_cannot_perform_any_lifecycle_transition(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->actingAsMemberOf($school, Role::Teacher);

        $this->post("/results/runs/{$run->id}/review")->assertForbidden();
        $this->post("/results/runs/{$run->id}/approve")->assertForbidden();
        $this->post("/results/runs/{$run->id}/publish")->assertForbidden();
        $this->post("/results/runs/{$run->id}/lock")->assertForbidden();
        $this->post("/results/runs/{$run->id}/compile")->assertForbidden();

        $this->assertSame(ResultRunStatus::Compiled, $run->fresh()->status);
    }

    public function test_a_view_only_role_cannot_approve_or_publish(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/review");
        $this->flushSession();

        $this->actingAsMemberOf($school, Role::Staff);
        $this->post("/results/runs/{$run->id}/approve")->assertForbidden();
        $this->assertSame(ResultRunStatus::Reviewed, $run->fresh()->status);
    }

    public function test_a_locked_run_rejects_recompilation(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/compile")
            ->assertSessionHas('error');

        $this->assertSame(ResultRunStatus::Locked, $run->fresh()->status);
    }

    public function test_a_run_with_compiled_results_cannot_be_deleted(): void
    {
        [$school, $run] = $this->compiledRun();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/results/runs/{$run->id}")
            ->assertRedirect(route('results.runs.show', $run->id))
            ->assertSessionHas('error');

        $this->assertNotNull($run->fresh());
    }
}
