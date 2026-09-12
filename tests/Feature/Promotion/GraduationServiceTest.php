<?php

namespace Tests\Feature\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\User;
use App\Services\Promotion\GraduationService;
use App\Services\Promotion\PromotionException;

class GraduationServiceTest extends PromotionTestCase
{
    private function service(): GraduationService
    {
        return app(GraduationService::class);
    }

    public function test_graduating_an_active_student_sets_the_lifecycle_status_and_metadata(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $by = User::factory()->create();
        $this->service()->graduate($student, $context['session'], 'Completed the programme.', $by);

        $student->refresh();
        $this->assertSame(StudentStatus::Graduated, $student->status);
        $this->assertNotNull($student->graduated_at);
        $this->assertSame($context['session']->id, $student->graduated_academic_session_id);
        $this->assertSame('Completed the programme.', $student->graduation_notes);
        $this->assertSame($by->id, $student->graduated_by);
    }

    public function test_graduating_closes_the_current_enrollment_but_preserves_it(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $enrollmentId = $student->currentEnrollment->id;
        $this->service()->graduate($student, $context['session'], null, User::factory()->create());

        $enrollment = $student->enrollments()->find($enrollmentId);
        $this->assertNotNull($enrollment, 'the enrollment row is never deleted');
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertNotNull($enrollment->ended_on);
        $this->assertSame($context['level']->id, $enrollment->academic_level_id, 'the historical placement itself is untouched');
    }

    public function test_graduating_a_student_with_no_current_enrollment_still_succeeds(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $session = AcademicSession::factory()->create();
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->enterSchool($school);
        $this->service()->graduate($student, $session, null, User::factory()->create());

        $this->assertSame(StudentStatus::Graduated, $student->fresh()->status);
    }

    public function test_graduating_an_already_graduated_student_throws(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->enterSchool($school);
        $this->expectException(PromotionException::class);
        $this->service()->graduate($student, $context['session'], null, User::factory()->create());
    }

    public function test_graduating_a_withdrawn_student_throws(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Withdrawn->value]);

        $this->enterSchool($school);
        $this->expectException(PromotionException::class);
        $this->service()->graduate($student, $context['session'], null, User::factory()->create());
    }

    public function test_graduate_batch_isolates_one_students_failure_from_the_rest(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $ok = $this->enrolledStudent($school, $context);
        $alreadyGraduated = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->enterSchool($school);
        $result = $this->service()->graduateBatch(collect([$ok, $alreadyGraduated]), $context['session'], null, User::factory()->create());

        $this->assertSame(1, $result['graduated']);
        $this->assertSame(1, $result['failed']);
        $this->assertArrayHasKey($alreadyGraduated->id, $result['failures']);
        $this->assertSame(StudentStatus::Graduated, $ok->fresh()->status);
    }

    public function test_reactivate_reverses_the_graduation(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $this->service()->graduate($student, $context['session'], 'Notes.', User::factory()->create());
        $this->service()->reactivate($student->fresh());

        $student->refresh();
        $this->assertSame(StudentStatus::Active, $student->status);
        $this->assertNull($student->graduated_at);
        $this->assertNull($student->graduated_academic_session_id);
        $this->assertNull($student->graduation_notes);
        $this->assertNull($student->graduated_by);
    }

    public function test_reactivate_a_non_graduated_student_throws(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $this->expectException(PromotionException::class);
        $this->service()->reactivate($student);
    }

    public function test_a_graduated_students_historical_enrollment_survives_reactivation(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $enrollmentId = $student->currentEnrollment->id;
        $this->service()->graduate($student, $context['session'], null, User::factory()->create());
        $this->service()->reactivate($student->fresh());

        $this->assertNotNull($student->enrollments()->find($enrollmentId));
    }

    public function test_a_graduated_student_cannot_receive_a_new_enrollment_via_the_existing_enrollment_request_guard(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context, ['status' => StudentStatus::Graduated->value]);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post("/students/{$student->id}/enrollments", [
            'academic_session_id' => $context['session']->id,
            'academic_period_id' => $context['period']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
            'status' => 'active',
            'started_on' => '2025-09-15',
        ]);

        $response->assertSessionHasErrors('academic_session_id');
    }
}
