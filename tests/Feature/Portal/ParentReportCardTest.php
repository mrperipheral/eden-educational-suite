<?php

namespace Tests\Feature\Portal;

use App\Models\ReportCardConfiguration;
use App\Models\ResultRun;
use App\Services\Results\ResultCompiler;

/**
 * Report-card visibility and reuse for the Parent Portal (see
 * `docs/parent-portal.md` §"Report card reuse" / §"Report card visibility").
 * `show()` renders through the exact same `App\Services\Results\
 * ReportCardRenderer` the school/staff side uses — there is no second
 * generator to drift out of sync.
 */
class ParentReportCardTest extends ParentPortalTestCase
{
    public function test_an_unpublished_report_card_is_not_reachable(): void
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
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/report-cards")
            ->assertOk()
            ->assertSee(__('No report card has been published for this student yet'));
        $this->get("/parent/children/{$students[0]->id}/report-cards/{$run->id}")->assertNotFound();
    }

    public function test_a_published_report_card_renders_via_the_shared_renderer(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $run = $this->publishedRun($school, $scaffold, $students, locked: false);

        $this->actingAsParent($school, $user);
        $response = $this->get("/parent/children/{$students[0]->id}/report-cards/{$run->id}")->assertOk();
        $response->assertSee($students[0]->fullName());
        $response->assertSee($school->name);
        // The audience-aware back-link points into the Parent Portal, not the
        // staff-only run page.
        $response->assertSee(route('parent.report-cards.index', $students[0]->id), false);
        $response->assertDontSee(route('results.runs.show', $run->id), false);
    }

    public function test_a_locked_report_card_stays_reachable_and_historically_accurate(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $run = $this->publishedRun($school, $scaffold, $students, locked: true);

        $this->enterSchool($school);
        ReportCardConfiguration::snapshotForRun($run);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/report-cards/{$run->id}")->assertOk();
    }

    public function test_a_report_card_for_a_different_child_is_inaccessible(): void
    {
        $school = $this->newSchool();
        [$userA, , $studentsA] = $this->parentWithChildren($school, 1);
        [, , $studentsB] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $studentsA);
        $this->enrollInScaffold($school, $scaffold, $studentsB);
        $run = $this->publishedRun($school, $scaffold, $studentsA->concat($studentsB));

        $this->actingAsParent($school, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}/report-cards/{$run->id}")->assertNotFound();
    }

    public function test_a_report_card_from_another_school_is_inaccessible(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->parentWithChildren($schoolA, 1);
        [, , $studentsB] = $this->parentWithChildren($schoolB, 1);
        $scaffoldB = $this->scaffold($schoolB);
        $this->enrollInScaffold($schoolB, $scaffoldB, $studentsB);
        $runB = $this->publishedRun($schoolB, $scaffoldB, $studentsB);

        $this->actingAsParent($schoolA, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}/report-cards/{$runB->id}")->assertNotFound();
    }
}
