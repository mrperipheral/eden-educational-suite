<?php

namespace Tests\Feature\Portal;

use App\Models\ResultRun;
use App\Models\SchoolModule;
use App\Services\Results\ResultCompiler;

/**
 * Result visibility for the Student Portal — published/locked only, own
 * record only (see `docs/student-portal.md` §"Results").
 */
class StudentResultTest extends StudentPortalTestCase
{
    public function test_an_approved_but_unpublished_run_is_not_visible(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));

        $this->enterSchool($school);
        $admin = $this->adminUser($school);
        $this->app->forgetScopedInstances();
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], collect([$student]), [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], collect([$student]), [20]);

        $this->enterSchool($school);
        $run = ResultRun::factory()->create([
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'grading_scheme_id' => $scaffold['grading']->id, 'result_weighting_scheme_id' => $scaffold['weighting']->id,
        ]);
        app(ResultCompiler::class)->compile($run, $admin);
        $run->refresh();
        $run->review($admin);
        $run->approve($admin);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/results')
            ->assertOk()
            ->assertSee(__('No published results are available yet'));
        $this->get("/student/results/{$run->id}")->assertNotFound();
    }

    public function test_a_published_run_is_visible_with_the_correct_breakdown(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $run = $this->publishedRun($school, $scaffold, collect([$student]), locked: false);

        $this->actingAsStudentUser($school, $user);

        $index = $this->get('/student/results')->assertOk();
        $index->assertSee($scaffold['session']->name);

        $show = $this->get("/student/results/{$run->id}")->assertOk();
        // 50% classwork * 40% + 100% exam * 60% = 80%, grade A.
        $show->assertSee('80');
        $show->assertSee('A');
    }

    public function test_a_locked_run_is_visible(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $run = $this->publishedRun($school, $scaffold, collect([$student]), locked: true);

        $this->actingAsStudentUser($school, $user);
        $this->get("/student/results/{$run->id}")->assertOk();
    }

    public function test_a_result_from_another_school_is_inaccessible(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->studentWithAccount($schoolA);
        [, $studentB] = $this->studentWithAccount($schoolB);
        $scaffoldB = $this->scaffold($schoolB);
        $this->enrollInScaffold($schoolB, $scaffoldB, collect([$studentB]));
        $runB = $this->publishedRun($schoolB, $scaffoldB, collect([$studentB]));

        $this->actingAsStudentUser($schoolA, $userA);
        $this->get("/student/results/{$runB->id}")->assertNotFound();
    }

    public function test_results_are_unavailable_when_the_results_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'results', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/results')
            ->assertOk()
            ->assertSee(__('Results are not currently available'));
    }
}
