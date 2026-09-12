<?php

namespace Tests\Feature\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Services\Promotion\PromotionEligibilityService;

class PromotionEligibilityTest extends PromotionTestCase
{
    private function service(): PromotionEligibilityService
    {
        return app(PromotionEligibilityService::class);
    }

    public function test_active_student_with_matching_active_enrollment_is_eligible(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $eligible = $this->service()->eligibleStudents($context['session'], $context['level'], $context['arm']);

        $this->assertTrue($eligible->pluck('id')->contains($student->id));
    }

    public function test_graduated_student_is_not_eligible(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->enterSchool($school);
        $eligible = $this->service()->eligibleStudents($context['session'], $context['level'], $context['arm']);

        $this->assertFalse($eligible->pluck('id')->contains($student->id));
    }

    public function test_withdrawn_student_is_not_eligible(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Withdrawn->value]);

        $this->enterSchool($school);
        $eligible = $this->service()->eligibleStudents($context['session'], $context['level'], $context['arm']);

        $this->assertFalse($eligible->pluck('id')->contains($student->id));
    }

    public function test_student_whose_active_enrollment_is_a_different_arm_is_not_eligible(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $this->enterSchool($school);
        $otherArm = $context['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();

        $student = $this->enrolledStudent($school, $context, [], ['level_arm_id' => $otherArm->id]);

        $this->enterSchool($school);
        $eligible = $this->service()->eligibleStudents($context['session'], $context['level'], $context['arm']);

        $this->assertFalse($eligible->pluck('id')->contains($student->id));
    }

    public function test_student_no_longer_actively_enrolled_in_the_source_class_is_not_eligible(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, [], ['status' => EnrollmentStatus::Completed->value, 'ended_on' => '2025-12-01']);

        $this->enterSchool($school);
        $eligible = $this->service()->eligibleStudents($context['session'], $context['level'], $context['arm']);

        $this->assertFalse($eligible->pluck('id')->contains($student->id));
    }

    public function test_a_student_from_another_school_never_appears_in_the_eligible_list(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $contextA = $this->classContext($schoolA);
        $this->enrolledStudent($schoolA, $contextA);

        $contextB = $this->classContext($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $contextB);

        $this->enterSchool($schoolA);
        $eligibleForA = $this->service()->eligibleStudents($contextA['session'], $contextA['level'], $contextA['arm']);

        $this->assertFalse($eligibleForA->pluck('id')->contains($studentB->id));
    }

    public function test_already_in_target_session_is_true_for_any_existing_enrollment_regardless_of_status(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);
        $target = $this->classContext($school);

        $this->enterSchool($school);
        $student->enrollments()->create([
            'academic_session_id' => $target['session']->id,
            'academic_level_id' => $target['level']->id,
            'level_arm_id' => $target['arm']->id,
            'status' => EnrollmentStatus::Completed->value,
            'started_on' => $target['session']->starts_on->toDateString(),
            'ended_on' => $target['session']->starts_on->toDateString(),
        ]);

        $this->assertTrue($this->service()->alreadyInTargetSession($student, $target['session']));
    }

    public function test_not_yet_in_target_session_is_false(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);
        $target = $this->classContext($school);

        $this->enterSchool($school);

        $this->assertFalse($this->service()->alreadyInTargetSession($student, $target['session']));
    }
}
