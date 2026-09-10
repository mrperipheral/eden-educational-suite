<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Relationships the later Guardian / Attendance / Assessment / Fees / Portal
 * modules build on, plus the "current placement is derived, not stored" rule
 * and the list's query behaviour.
 */
class StudentStructureTest extends StudentTestCase
{
    public function test_school_owns_its_students_and_students_own_their_enrollments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);

        $students = Student::factory()->count(3)->create();
        $students->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]));

        $this->assertSame(3, $school->students()->count());
        $this->assertSame(1, $students->first()->enrollments()->count());
        $this->assertSame(3, Enrollment::query()->count());
    }

    public function test_current_placement_is_the_active_enrollment_not_a_column(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $student = Student::factory()->create();

        $this->assertNull($student->currentEnrollment);

        $old = $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2024-09-15',
        ]);
        $old->makeActive();

        $new = $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]);
        $new->makeActive();

        $this->assertTrue($student->fresh()->currentEnrollment->is($new));
        $this->assertSame(EnrollmentStatus::Completed, $old->fresh()->status);

        // No `current_*` columns leaked onto students.
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('students', 'academic_level_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('students', 'current_enrollment_id'));
    }

    public function test_a_withdrawn_student_keeps_their_record_and_history(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]);

        $student->status = StudentStatus::Withdrawn;
        $student->save();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'withdrawn']);
        $this->assertSame(1, $student->enrollments()->count());
    }

    public function test_the_student_list_does_not_n_plus_one_on_placement(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        Student::factory()->count(12)->create()->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => '2025-09-15',
        ]));
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/students')->assertOk()->assertSee($scaffold['level']->name);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // students page + count + eager loads — a small constant, not ~1 per row.
        $this->assertLessThan(15, $queries, "student list ran {$queries} queries for 12 students");
    }
}
