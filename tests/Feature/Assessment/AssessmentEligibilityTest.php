<?php

namespace Tests\Feature\Assessment;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AssessmentScore;
use App\Models\Student;

/**
 * The eligibility rule (see `docs/assessment-management.md` § eligibility):
 * a student is on an assessment iff they hold an Enrollment for that exact
 * (session, level, arm) whose [started_on, ended_on] range contains the
 * assessment date. Same historical principle as M13 attendance.
 */
class AssessmentEligibilityTest extends AssessmentTestCase
{
    private function rosterStudentIds(int $schoolId): array
    {
        return AssessmentScore::query()->withoutGlobalScopes()->where('school_id', $schoolId)
            ->pluck('student_id')->sort()->values()->all();
    }

    public function test_only_students_enrolled_in_the_exact_class_are_on_the_assessment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $inClass = $this->enrolledStudents($school, $scaffold, 3);

        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        Student::factory()->create()->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $otherArm->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);
        Student::factory()->create();   // no enrollment at all
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/assessments', $this->assessmentPayload($scaffold))->assertRedirect();

        $this->assertSame($inClass->pluck('id')->sort()->values()->all(), $this->rosterStudentIds($school->id));
    }

    public function test_a_student_not_yet_enrolled_on_the_assessment_date_is_excluded(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $early = $this->enrolledStudents($school, $scaffold, 1)->first();

        $this->enterSchool($school);
        Student::factory()->create()->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => now()->addDay()->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/assessments', $this->assessmentPayload($scaffold))->assertRedirect();

        $this->assertSame([$early->id], $this->rosterStudentIds($school->id));
    }

    public function test_a_student_who_left_before_the_date_is_excluded_but_one_leaving_after_is_included(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        Student::factory()->create()->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Withdrawn->value,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => now()->subDays(10)->toDateString(),
        ]);
        $stillHere = Student::factory()->status(StudentStatus::Withdrawn)->create();
        $stillHere->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Withdrawn->value,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => now()->addDays(5)->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/assessments', $this->assessmentPayload($scaffold))->assertRedirect();

        $this->assertSame([$stillHere->id], $this->rosterStudentIds($school->id));
    }

    public function test_a_cross_school_student_can_never_be_scored(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA, 2);
        $assessmentA = $this->assessmentFor($a, $scaffoldA);
        $this->snapshotRoster($a, $assessmentA);

        $scaffoldB = $this->scaffold($b);
        $studentB = $this->enrolledStudents($b, $scaffoldB, 1)->first();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->from(route('assessments.scores.edit', $assessmentA->id))
            ->patch("/assessments/{$assessmentA->id}/scores", ['scores' => [$studentB->id => ['score' => 5]]])
            ->assertSessionHasErrors('scores');

        $this->assertSame(0, AssessmentScore::query()->withoutGlobalScopes()
            ->where('assessment_id', $assessmentA->id)->where('student_id', $studentB->id)->count());
    }

    public function test_the_draft_roster_can_be_synced_with_current_enrolment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->enterSchool($school);
        $this->assertSame(2, $assessment->scores()->count());

        // A new student joins the class before the assessment date.
        Student::factory()->create()->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => now()->subDays(5)->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/assessments/{$assessment->id}/scores/sync")->assertRedirect();

        $this->enterSchool($school);
        $this->assertSame(3, $assessment->scores()->count());
    }

    public function test_a_published_roster_cannot_be_synced(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->post("/assessments/{$assessment->id}/scores/sync")
            ->assertSessionHas('error');
    }
}
