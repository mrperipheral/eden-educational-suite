<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;
use App\Models\Student;
use App\Support\Portal\StudentPortalAuthorizer;

/**
 * The core ownership guarantee: a student may only ever see their own
 * record — never another student's, never by tampering with a `{run}` id,
 * never across a school boundary (see `docs/student-portal.md`
 * §"Tenant isolation").
 */
class StudentAccessTest extends StudentPortalTestCase
{
    public function test_a_student_sees_only_their_own_profile(): void
    {
        $school = $this->newSchool();
        [$userA, $studentA] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        $studentB = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $userA);
        $response = $this->get('/student/profile')->assertOk();
        $response->assertSee($studentA->admission_number);
        $response->assertDontSee($studentB->admission_number);
    }

    public function test_a_run_that_does_not_include_this_student_is_inaccessible(): void
    {
        // studentB has a compiled result in this run; studentA does not (a
        // different class/run entirely) — studentA must never reach it via
        // its run id, even though the run itself exists and is published.
        $school = $this->newSchool();
        [$userA] = $this->studentWithAccount($school);
        [, $studentB] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$studentB]));
        $run = $this->publishedRun($school, $scaffold, collect([$studentB]));

        $this->actingAsStudentUser($school, $userA);
        $this->get("/student/results/{$run->id}")->assertNotFound();
        $this->get("/student/report-cards/{$run->id}")->assertNotFound();
    }

    public function test_a_student_from_another_school_is_never_reachable_via_a_shared_run_id(): void
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
        $this->get("/student/report-cards/{$runB->id}")->assertNotFound();
    }

    public function test_a_students_own_account_link_cannot_be_changed_by_the_student(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        $otherStudent = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->patch("/students/{$otherStudent->id}/user", ['user_id' => $user->id])->assertForbidden();
        $this->assertNull($otherStudent->fresh()->user_id);
        $this->assertSame($student->id, $this->app->make(StudentPortalAuthorizer::class)->studentFor($user->fresh())?->id);
    }

    public function test_a_student_with_no_linked_record_cannot_reach_a_report_card_by_guessing_a_run_id(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school, Role::Student);
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $this->app->forgetScopedInstances();
        $run = $this->publishedRun($school, $scaffold, collect([$student]));

        $this->actingAsStudentUser($school, $user);
        $this->get("/student/results/{$run->id}")->assertNotFound();
        $this->get("/student/report-cards/{$run->id}")->assertNotFound();
    }
}
