<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AttendanceRegister;
use App\Models\School;
use App\Reports\AttendanceReport;
use Illuminate\Support\Collection;

/**
 * `AttendanceReport` (M27 §4) — only `submitted` registers are ever counted;
 * "present" means `AttendanceStatus::isAttending()` (present + late).
 */
class AttendanceReportTest extends ReportsTestCase
{
    private function submittedRegister(School $school, array $scaffold, Collection $students, array $statuses, array $overrides = []): AttendanceRegister
    {
        $this->enterSchool($school);
        $register = AttendanceRegister::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'submitted',
        ], $overrides));

        $students->values()->each(function ($student, int $i) use ($register, $statuses) {
            $register->records()->create(['student_id' => $student->id, 'status' => $statuses[$i % count($statuses)]]);
        });

        $this->app->forgetScopedInstances();

        return $register;
    }

    public function test_student_attendance_counts_present_absent_late_excused(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->submittedRegister($school, $scaffold, $students, ['present']);
        $this->submittedRegister($school, $scaffold, $students, ['absent'], ['attendance_date' => now()->subDays(2)->toDateString()]);
        $this->submittedRegister($school, $scaffold, $students, ['late'], ['attendance_date' => now()->subDays(3)->toDateString()]);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(AttendanceReport::class)->studentAttendance([], $admin);

        $row = $page->items()[0];
        $this->assertSame(3, (int) $row->days_marked);
        // days_present counts present+late together (the "isAttending()" convention documented on AttendanceReport).
        $this->assertSame(2, (int) $row->days_present);
        $this->assertSame(1, (int) $row->days_absent);
        $this->assertSame(1, (int) $row->days_late);
    }

    public function test_draft_register_is_not_counted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->submittedRegister($school, $scaffold, $students, ['present'], ['status' => 'draft']);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(AttendanceReport::class)->studentAttendance([], $admin);

        $this->assertSame(0, $page->total());
    }

    public function test_class_attendance_computes_percentage(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);

        $this->submittedRegister($school, $scaffold, $students, ['present', 'absent']);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $rows = app(AttendanceReport::class)->classAttendance([], $admin);

        $this->assertCount(1, $rows);
        $this->assertSame(50.0, $rows[0]['attendance_percentage']);
    }

    public function test_trend_returns_one_row_per_date(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->submittedRegister($school, $scaffold, $students, ['present'], ['attendance_date' => now()->subDays(1)->toDateString()]);
        $this->submittedRegister($school, $scaffold, $students, ['absent'], ['attendance_date' => now()->subDays(2)->toDateString()]);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $rows = app(AttendanceReport::class)->trend([], $admin);

        $this->assertCount(2, $rows);
    }

    public function test_teacher_without_manage_only_sees_their_assigned_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->submittedRegister($school, $scaffold, $students, ['present']);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        AttendanceRegister::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $otherLevel->id,
            'level_arm_id' => $otherArm->id,
            'attendance_date' => now()->subDay()->toDateString(),
            'status' => 'submitted',
        ]);
        $this->app->forgetScopedInstances();

        $teacher = $this->actingAsTeacherFor($school, $scaffold);
        $this->enterSchool($school);
        $rows = app(AttendanceReport::class)->classAttendance([], $teacher);

        $this->assertCount(1, $rows);
        $this->assertSame($scaffold['level']->id, $rows[0]['academic_level_id']);
    }

    public function test_disabled_attendance_module_does_not_break_reporting_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->disableModule($school, 'attendance');

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $rows = app(AttendanceReport::class)->classAttendance([], $admin);
        $this->assertCount(0, $rows);
    }
}
