<?php

namespace Tests\Feature\Results;

use App\Enums\ResultRunStatus;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\GradingScheme;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class ResultRunTest extends ResultsTestCase
{
    private function rowsFor(int $schoolId)
    {
        return ResultRun::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_a_result_run_is_created_in_draft(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/runs', $this->runPayload($scaffold))->assertRedirect();

        $run = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($school->id, $run->school_id);
        $this->assertSame(ResultRunStatus::Draft, $run->status);
        $this->assertTrue($run->ranking_enabled);
    }

    public function test_required_fields_are_validated(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/runs/create')->post('/results/runs', [])->assertSessionHasErrors([
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id',
            'grading_scheme_id', 'result_weighting_scheme_id',
        ]);
    }

    public function test_a_period_from_another_session_and_an_arm_from_another_level_are_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2020-01-01', 'ends_on' => '2020-04-01', 'position' => 1]);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffold, ['academic_period_id' => $otherPeriod->id]))
            ->assertSessionHasErrors('academic_period_id');
        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');
    }

    public function test_an_incomplete_weighting_scheme_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $incomplete = ResultWeightingScheme::factory()->create();
        $incomplete->items()->create(['assessment_category_id' => $scaffold['classwork']->id, 'weight_percentage' => 50]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffold, ['result_weighting_scheme_id' => $incomplete->id]))
            ->assertSessionHasErrors('result_weighting_scheme_id');
    }

    public function test_an_inactive_scheme_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $inactive = GradingScheme::factory()->inactive()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffold, ['grading_scheme_id' => $inactive->id]))
            ->assertSessionHasErrors('grading_scheme_id');
    }

    public function test_only_one_run_per_class_and_term_is_allowed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/runs', $this->runPayload($scaffold))->assertSessionHasNoErrors();
        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffold))
            ->assertSessionHasErrors('level_arm_id');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_the_same_class_can_have_a_run_in_a_different_term(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $secondTerm = $scaffold['session']->periods()->create(['name' => 'Second Term', 'starts_on' => now()->addMonths(3)->toDateString(), 'ends_on' => now()->addMonths(6)->toDateString(), 'position' => 2]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/runs', $this->runPayload($scaffold))->assertSessionHasNoErrors();
        $this->post('/results/runs', $this->runPayload($scaffold, ['academic_period_id' => $secondTerm->id]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_the_list_filters_by_class_and_status(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();
        $this->resultRun($school, $scaffold, ['academic_level_id' => $otherLevel->id, 'level_arm_id' => $otherArm->id]);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/results/runs')->assertOk()->assertViewHas('runs', fn ($p) => $p->total() === 2);
        $this->get('/results/runs?level='.$scaffold['level']->id)->assertOk()->assertViewHas('runs', fn ($p) => $p->total() === 1);
    }

    public function test_result_runs_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $run = $this->resultRun($a, $scaffoldA);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/results/runs')->assertOk()->assertViewHas('runs', fn ($p) => $p->total() === 0);
        $this->get("/results/runs/{$run->id}")->assertNotFound();
        $this->post("/results/runs/{$run->id}/compile")->assertNotFound();
        $this->post("/results/runs/{$run->id}/review")->assertNotFound();
        $this->post("/results/runs/{$run->id}/approve")->assertNotFound();
        $this->post("/results/runs/{$run->id}/publish")->assertNotFound();
        $this->post("/results/runs/{$run->id}/lock")->assertNotFound();
        $this->delete("/results/runs/{$run->id}")->assertNotFound();

        $this->assertSame(ResultRunStatus::Draft, $run->fresh()->status);
    }

    public function test_a_run_cannot_be_created_with_another_schools_context(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from('/results/runs/create')->post('/results/runs', $this->runPayload($scaffoldA))
            ->assertSessionHasErrors(['academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id', 'grading_scheme_id', 'result_weighting_scheme_id']);

        $this->assertSame(0, $this->rowsFor($a->id)->count());
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_school_ownership_is_immutable_and_not_spoofable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/results/runs', $this->runPayload($scaffoldA, ['school_id' => $b->id]))->assertRedirect();
        $run = $this->rowsFor($a->id)->firstOrFail();
        $this->assertSame($a->id, $run->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());

        $this->expectException(TenantMismatchException::class);
        $run->school_id = $b->id;
        $run->save();
    }

    public function test_a_draft_run_with_no_results_can_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/results/runs/{$run->id}")->assertRedirect(route('results.runs.index'));

        $this->assertNull($run->fresh());
    }
}
