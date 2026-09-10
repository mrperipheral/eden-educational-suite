<?php

namespace Tests\Feature\Attendance;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AttendanceRegister;

class AttendanceAuthorizationTest extends AttendanceTestCase
{
    private function rowsFor(int $schoolId)
    {
        return AttendanceRegister::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_managers_can_create_a_register_for_a_class_they_are_not_assigned_to(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);

        $day = 1;
        foreach ($this->attendanceRoles()['manage'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->post('/attendance', $this->registerPayload($scaffold, [
                'attendance_date' => now()->subDays($day++)->toDateString(),
            ]))->assertSessionHasNoErrors();
            $this->flushSession();
        }

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_can_record_only_for_a_class_they_are_assigned_to(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        // A different class the teacher is NOT assigned to (but which has students).
        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $otherScaffold = ['session' => $scaffold['session'], 'period' => $scaffold['period'], 'level' => $otherLevel, 'arm' => $otherArm];
        $this->app->forgetScopedInstances();
        $this->enrolledStudents($school, $otherScaffold, 2);

        $this->actingAsTeacherFor($school, $scaffold);   // assigned to $scaffold's class only

        $this->post('/attendance', $this->registerPayload($scaffold))->assertSessionHasNoErrors();
        $this->from('/attendance/create')->post('/attendance', $this->registerPayload($otherScaffold))->assertForbidden();

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_with_no_linked_teacher_record_cannot_record(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::Teacher);   // Teacher role, but no Teacher record / assignment

        $this->from('/attendance/create')->post('/attendance', $this->registerPayload($scaffold))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_assignment_in_another_school_does_not_grant_access(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $this->enrolledStudents($b, $scaffoldB);

        // The same user is a Teacher in both schools, but only assigned in A.
        $teacher = $this->actingAsTeacherFor($a, $scaffoldA);
        $teacher->joinSchool($b, Role::Teacher);

        $this->actingAs($teacher);
        $this->withSession([EnforceTenant::SESSION_KEY => $b->getKey()]);

        $this->from('/attendance/create')->post('/attendance', $this->registerPayload($scaffoldB))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_view_only_roles_can_read_but_not_create_or_record(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);

        foreach ($this->attendanceRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/attendance')->assertOk();
            $this->get("/attendance/{$register->id}")->assertOk();
            $this->get('/attendance/create')->assertForbidden();
            $this->from('/attendance')->post('/attendance', $this->registerPayload($scaffold, ['attendance_date' => now()->subDays(3)->toDateString()]))->assertForbidden();
            $this->patch("/attendance/{$register->id}/records", ['records' => [$students[0]->id => ['status' => 'present']]])->assertForbidden();
            $this->post("/attendance/{$register->id}/reopen")->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        foreach ($this->attendanceRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/attendance')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_routes_are_unavailable_when_the_attendance_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableAttendance($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/attendance')->assertNotFound();
        $this->get('/attendance/create')->assertNotFound();
        $this->post('/attendance', [])->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permission(): void
    {
        // Attendance module on by default; a Bursar still cannot see attendance.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get('/attendance')->assertForbidden();
    }
}
