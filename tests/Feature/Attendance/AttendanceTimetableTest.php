<?php

namespace Tests\Feature\Attendance;

use App\Enums\Module;
use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolModule;

/**
 * Timetable integration decision (see `docs/attendance-management.md` §6):
 * attendance is fully independent of the Timetable module. There is no stored
 * link to a timetable or a lesson; a school with the timetable turned off
 * records attendance exactly the same way.
 */
class AttendanceTimetableTest extends AttendanceTestCase
{
    private function disableTimetable(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => Module::Timetable->value, 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    public function test_the_attendance_module_does_not_depend_on_the_timetable(): void
    {
        $this->assertNotContains(Module::Timetable, Module::Attendance->dependencies());
        $this->assertSame([Module::Academics, Module::Students], Module::Attendance->dependencies());
    }

    public function test_the_full_workflow_runs_with_the_timetable_module_disabled(): void
    {
        $school = $this->newSchool();
        $this->disableTimetable($school);
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // Create the register.
        $this->post('/attendance', $this->registerPayload($scaffold))->assertRedirect();
        $register = $school->attendanceRegisters()->firstOrFail();

        // Mark everyone and submit.
        $marks = [];
        foreach ($students as $s) {
            $marks[$s->id] = ['status' => 'present'];
        }
        $this->patch("/attendance/{$register->id}/records", ['records' => $marks, 'submit' => '1'])
            ->assertSessionHasNoErrors();

        $register->refresh();
        $this->assertTrue($register->isLocked());
        $this->assertSame($admin->id, $register->submitted_by);
    }

    public function test_a_teacher_records_attendance_without_a_timetable(): void
    {
        $school = $this->newSchool();
        $this->disableTimetable($school);
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $this->actingAsTeacherFor($school, $scaffold);
        $this->post('/attendance', $this->registerPayload($scaffold))->assertSessionHasNoErrors();

        $this->assertSame(1, $school->attendanceRegisters()->count());
    }

    public function test_turning_the_timetable_back_on_changes_nothing_about_attendance(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => Module::Timetable->value, 'enabled' => true]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/attendance', $this->registerPayload($scaffold))->assertRedirect();

        $register = $school->attendanceRegisters()->firstOrFail();
        $this->assertSame(2, $register->records()->count());
    }
}
