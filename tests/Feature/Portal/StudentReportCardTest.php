<?php

namespace Tests\Feature\Portal;

/**
 * Report-card visibility and reuse for the Student Portal (see
 * `docs/student-portal.md` §"Report cards").
 */
class StudentReportCardTest extends StudentPortalTestCase
{
    public function test_a_published_report_card_renders_via_the_shared_renderer(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $run = $this->publishedRun($school, $scaffold, collect([$student]), locked: false);

        $this->actingAsStudentUser($school, $user);
        $response = $this->get("/student/report-cards/{$run->id}")->assertOk();
        $response->assertSee($student->fullName());
        $response->assertSee($school->name);
        $response->assertSee(route('student.report-cards.index'), false);
        $response->assertDontSee(route('results.runs.show', $run->id), false);
    }

    public function test_a_locked_report_card_stays_reachable(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $run = $this->publishedRun($school, $scaffold, collect([$student]), locked: true);

        $this->actingAsStudentUser($school, $user);
        $this->get("/student/report-cards/{$run->id}")->assertOk();
    }

    public function test_an_unpublished_report_card_index_shows_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->actingAsStudentUser($school, $user);

        $this->get('/student/report-cards')
            ->assertOk()
            ->assertSee(__('No report card has been published yet'));
    }

    public function test_a_report_card_from_another_school_is_inaccessible(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->studentWithAccount($schoolA);
        [, $studentB] = $this->studentWithAccount($schoolB);
        $scaffoldB = $this->scaffold($schoolB);
        $this->enrollInScaffold($schoolB, $scaffoldB, collect([$studentB]));
        $runB = $this->publishedRun($schoolB, $scaffoldB, collect([$studentB]));

        $this->actingAsStudentUser($schoolA, $userA);
        $this->get("/student/report-cards/{$runB->id}")->assertNotFound();
    }
}
