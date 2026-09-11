<?php

namespace Tests\Feature\Portal;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRegister;
use App\Models\School;
use App\Models\SchoolModule;
use Illuminate\Support\Collection;

/**
 * Attendance visibility for the Student Portal — only the signed-in
 * student's own data (see `docs/student-portal.md` §"Attendance").
 */
class StudentAttendanceTest extends StudentPortalTestCase
{
    private function submittedRegister(School $school, array $scaffold, Collection $students, array $statuses): AttendanceRegister
    {
        $this->enterSchool($school);
        $admin = $this->adminUser($school);

        $register = AttendanceRegister::create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'attendance_date' => now()->subDays(2)->toDateString(),
        ]);
        $students->values()->each(fn ($student, $i) => $register->records()->create([
            'student_id' => $student->id,
            'status' => $statuses[$i % count($statuses)]->value,
        ]));
        $register->submit($admin);

        $this->app->forgetScopedInstances();

        return $register;
    }

    public function test_only_the_students_own_attendance_is_visible(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $this->submittedRegister($school, $scaffold, collect([$student]), [AttendanceStatus::Present]);

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/attendance')->assertOk()->assertSee('100%');
    }

    public function test_a_classmates_attendance_never_leaks(): void
    {
        $school = $this->newSchool();
        [$userA, $studentA] = $this->studentWithAccount($school);
        [, $studentB] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$studentA, $studentB]));
        // studentA present, studentB absent.
        $this->submittedRegister($school, $scaffold, collect([$studentA, $studentB]), [AttendanceStatus::Present, AttendanceStatus::Absent]);

        $this->actingAsStudentUser($school, $userA);
        $this->get('/student/attendance')->assertOk()->assertSee('100%');
    }

    public function test_no_attendance_data_yet_shows_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/attendance')
            ->assertOk()
            ->assertSee(__('No attendance information is available yet'));
    }

    public function test_attendance_degrades_gracefully_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'attendance', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/attendance')
            ->assertOk()
            ->assertSee(__('Attendance is not currently available'));
    }
}
