<?php

namespace Tests\Feature\Attendance;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AttendanceRecord;
use App\Models\Student;

/**
 * The eligibility rule (see `docs/attendance-management.md` §2):
 * a student is on a register iff they hold an Enrollment for that exact
 * (session, level, arm) whose [started_on, ended_on] range contains the date.
 */
class AttendanceEligibilityTest extends AttendanceTestCase
{
    private function rosterStudentIds(int $schoolId): array
    {
        return AttendanceRecord::query()->withoutGlobalScopes()->where('school_id', $schoolId)->pluck('student_id')->sort()->values()->all();
    }

    public function test_only_students_enrolled_in_the_selected_class_are_on_the_register(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $inClass = $this->enrolledStudents($school, $scaffold, 3);

        // A student in a different arm of the same level.
        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $elsewhere = Student::factory()->create();
        $elsewhere->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $otherArm->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);
        // A student with no enrollment at all.
        Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/attendance', $this->registerPayload($scaffold))->assertRedirect();

        $this->assertSame($inClass->pluck('id')->sort()->values()->all(), $this->rosterStudentIds($school->id));
    }

    public function test_a_student_not_yet_enrolled_on_the_date_is_excluded(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $early = $this->enrolledStudents($school, $scaffold, 1)->first();

        // Enrolled the day *after* the register date.
        $this->enterSchool($school);
        $late = Student::factory()->create();
        $late->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => now()->addDay()->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/attendance', $this->registerPayload($scaffold))->assertRedirect();

        $this->assertSame([$early->id], $this->rosterStudentIds($school->id));
    }

    public function test_a_student_whose_enrollment_ended_before_the_date_is_excluded_but_one_ending_after_is_included(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        $left = Student::factory()->create();
        $left->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Withdrawn->value,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => now()->subDays(10)->toDateString(),   // left before the register date
        ]);
        $stillHere = Student::factory()->status(StudentStatus::Withdrawn)->create();   // withdrawn *now*
        $stillHere->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Withdrawn->value,
            'started_on' => now()->subMonths(2)->toDateString(),
            'ended_on' => now()->addDays(5)->toDateString(),    // left after the register date
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/attendance', $this->registerPayload($scaffold))->assertRedirect();

        $this->assertSame([$stillHere->id], $this->rosterStudentIds($school->id),
            'a student in class on the date is on the register even if withdrawn later');
    }

    public function test_a_cross_school_student_can_never_be_added_to_a_register(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA, 2);
        $registerA = $this->registerFor($a, $scaffoldA);
        $this->snapshotRoster($a, $registerA);

        // A student that belongs to School B.
        $scaffoldB = $this->scaffold($b);
        $studentB = $this->enrolledStudents($b, $scaffoldB, 1)->first();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->from(route('attendance.show', $registerA->id))
            ->patch("/attendance/{$registerA->id}/records", [
                'records' => [$studentB->id => ['status' => 'present']],
            ])->assertSessionHasErrors('records');

        $this->assertSame(0, AttendanceRecord::query()->withoutGlobalScopes()
            ->where('attendance_register_id', $registerA->id)->where('student_id', $studentB->id)->count());
    }
}
