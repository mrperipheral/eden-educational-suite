<?php

namespace Tests\Feature\Portal;

use App\Models\ResultRun;
use App\Models\SchoolModule;
use App\Services\Results\ResultCompiler;

/**
 * Result visibility for the Parent Portal — published/locked only, own child
 * only, own school only (see `docs/parent-portal.md` §"Result visibility").
 */
class ParentResultTest extends ParentPortalTestCase
{
    public function test_an_approved_but_unpublished_run_is_not_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);

        $this->enterSchool($school);
        $admin = $this->adminUser($school);
        $this->app->forgetScopedInstances();
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);

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

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/results")
            ->assertOk()
            ->assertSee(__('No published results are available for this student yet'));
        $this->get("/parent/children/{$students[0]->id}/results/{$run->id}")->assertNotFound();
    }

    public function test_a_published_run_is_visible_with_the_correct_breakdown(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $run = $this->publishedRun($school, $scaffold, $students, locked: false);

        $this->actingAsParent($school, $user);

        $index = $this->get("/parent/children/{$students[0]->id}/results")->assertOk();
        $index->assertSee($scaffold['session']->name);

        $show = $this->get("/parent/children/{$students[0]->id}/results/{$run->id}")->assertOk();
        // 50% classwork * 40% + 100% exam * 60% = 80%, grade A.
        $show->assertSee('80');
        $show->assertSee('A');
    }

    public function test_a_locked_run_is_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $run = $this->publishedRun($school, $scaffold, $students, locked: true);

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/results/{$run->id}")->assertOk();
    }

    public function test_a_result_belonging_to_a_different_child_is_inaccessible(): void
    {
        $school = $this->newSchool();
        [$userA, , $studentsA] = $this->parentWithChildren($school, 1);
        [, , $studentsB] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $studentsA);
        $this->enrollInScaffold($school, $scaffold, $studentsB);
        $run = $this->publishedRun($school, $scaffold, $studentsA->concat($studentsB));

        $this->actingAsParent($school, $userA);
        // userA is not linked to studentsB — the whole page 404s regardless
        // of whether a result exists for that (other) child.
        $this->get("/parent/children/{$studentsB[0]->id}/results/{$run->id}")->assertNotFound();
    }

    public function test_a_result_from_another_school_is_inaccessible(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->parentWithChildren($schoolA, 1);
        [, , $studentsB] = $this->parentWithChildren($schoolB, 1);
        $scaffoldB = $this->scaffold($schoolB);
        $this->enrollInScaffold($schoolB, $scaffoldB, $studentsB);
        $runB = $this->publishedRun($schoolB, $scaffoldB, $studentsB);

        $this->actingAsParent($schoolA, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}/results/{$runB->id}")->assertNotFound();
    }

    public function test_results_are_unavailable_when_the_results_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'results', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/results")
            ->assertOk()
            ->assertSee(__('Results are not currently available'));
    }
}
