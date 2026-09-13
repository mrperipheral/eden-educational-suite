<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Reports\StaffReport;
use App\Reports\StudentReport;

/**
 * `StudentReport` (M27 §7) and `StaffReport` (M27 §8) — aggregate-only
 * enrollment/staff counts. `StaffReport::summary()`'s "workload" is a
 * scheduling fact (active assignment count), never an HR/payroll metric.
 */
class StudentStaffReportTest extends ReportsTestCase
{
    public function test_enrollment_summary_counts_by_status_and_level(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 3);

        $this->enterSchool($school);
        Student::factory()->create(['status' => 'withdrawn']);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(StudentReport::class)->enrollmentSummary([]);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, (int) ($summary['by_status']['active'] ?? 0));
        $this->assertSame(1, (int) ($summary['by_status']['withdrawn'] ?? 0));
        $this->assertSame(1, $summary['by_level']->count());
    }

    public function test_enrollment_summary_filters_by_level(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(StudentReport::class)->enrollmentSummary(['level' => $otherLevel->id]);

        $this->assertSame(0, $summary['total']);
    }

    public function test_enrollment_summary_scoped_to_this_school_only(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $other = $this->newSchool();
        $otherScaffold = $this->scaffold($other);
        $this->enrolledStudents($other, $otherScaffold, 5);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(StudentReport::class)->enrollmentSummary([]);

        $this->assertSame(2, $summary['total']);
    }

    public function test_staff_summary_counts_and_workload(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        $teacher = Teacher::factory()->create(['status' => 'active']);
        Teacher::factory()->create(['status' => 'inactive']);

        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(StaffReport::class)->summary();

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, (int) ($summary['by_status']['active'] ?? 0));
        $this->assertSame(1, (int) ($summary['by_status']['inactive'] ?? 0));
        $this->assertSame(1, $summary['workload']->count());
        $this->assertSame(1, $summary['workload']->first()->assignment_count);
    }
}
