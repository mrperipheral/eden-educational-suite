<?php

namespace Tests\Feature\Attendance;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domain boundaries and performance guarantees for M13:
 *   School → AttendanceRegisters · Register → Records ·
 *   Record → Register / Student
 * and nothing else — no timetable / result / fee coupling on the register, and
 * historical records survive a student leaving.
 */
class AttendanceStructureTest extends AttendanceTestCase
{
    public function test_school_owns_registers_and_registers_own_records(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->enterSchool($school);

        $this->assertSame(1, $school->attendanceRegisters()->count());
        $this->assertSame(3, $register->records()->count());

        $record = AttendanceRecord::query()->with(['register', 'student'])->first();
        $this->assertTrue($record->register->is($register));
        $this->assertTrue($students->contains($record->student));
    }

    public function test_the_register_table_carries_only_the_expected_columns(): void
    {
        $columns = Schema::getColumnListing('attendance_registers');
        sort($columns);

        $this->assertSame([
            'academic_level_id', 'academic_period_id', 'academic_session_id', 'attendance_date',
            'created_at', 'id', 'level_arm_id', 'notes', 'school_id', 'status',
            'submitted_at', 'submitted_by', 'updated_at',
        ], $columns);

        // Timetable is an optional integration, never a stored dependency.
        $this->assertFalse(Schema::hasColumn('attendance_registers', 'timetable_id'));
        $this->assertFalse(Schema::hasColumn('attendance_registers', 'timetable_entry_id'));
    }

    public function test_the_record_table_carries_only_the_expected_columns(): void
    {
        $columns = Schema::getColumnListing('attendance_records');
        sort($columns);

        $this->assertSame([
            'attendance_register_id', 'created_at', 'id', 'note', 'recorded_at', 'recorded_by',
            'school_id', 'status', 'student_id', 'updated_at',
        ], $columns);

        // No booleans — a single controlled status column (see AttendanceStatus).
        $this->assertFalse(Schema::hasColumn('attendance_records', 'is_present'));
        $this->assertFalse(Schema::hasColumn('attendance_records', 'is_absent'));
        $this->assertFalse(Schema::hasColumn('attendance_records', 'minutes_late'));
    }

    public function test_the_register_model_exposes_no_foreign_module_relationships(): void
    {
        $register = new AttendanceRegister;

        foreach (['timetable', 'timetableEntry', 'lesson', 'results', 'marks', 'fees', 'invoices'] as $relation) {
            $this->assertFalse(method_exists($register, $relation), "AttendanceRegister should not define `{$relation}()`");
        }
    }

    public function test_the_unique_index_stops_two_registers_for_one_class_on_one_day(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->registerFor($school, $scaffold, ['attendance_date' => $this->attendanceDate]);

        $this->expectException(QueryException::class);
        $this->registerFor($school, $scaffold, ['attendance_date' => $this->attendanceDate]);
    }

    public function test_a_record_stays_when_the_student_later_withdraws(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/attendance/{$register->id}/records", ['records' => [$student->id => ['status' => 'present']]]);

        // The student leaves the school after that day.
        $this->enterSchool($school);
        $student->enrollments()->update([
            'status' => EnrollmentStatus::Withdrawn->value,
            'ended_on' => now()->addDay()->toDateString(),
        ]);
        Student::withoutGlobalScopes()->whereKey($student->id)->update(['status' => StudentStatus::Withdrawn->value]);

        $record = $register->records()->where('student_id', $student->id)->first();
        $this->assertNotNull($record, 'the historical record is untouched');
        $this->assertSame('present', $record->status->value);
    }

    public function test_deleting_a_student_cascades_records_but_leaves_the_register(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->enterSchool($school);

        Student::withoutGlobalScopes()->whereKey($students->first()->id)->delete();

        $this->assertNotNull($register->fresh());
        $this->assertSame(1, $register->records()->count());
        $this->assertSame(0, $register->records()->where('student_id', $students->first()->id)->count());
    }

    public function test_deleting_a_register_leaves_students_and_academic_structure_intact(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->enterSchool($school);

        AttendanceRegister::withoutGlobalScopes()->whereKey($register->id)->delete();

        $this->assertSame(0, AttendanceRecord::query()->withoutGlobalScopes()->where('attendance_register_id', $register->id)->count());
        $this->assertDatabaseHas('students', ['id' => $students->first()->id]);
        $this->assertDatabaseHas('academic_levels', ['id' => $scaffold['level']->id]);
        $this->assertDatabaseHas('level_arms', ['id' => $scaffold['arm']->id]);
        $this->assertDatabaseHas('academic_sessions', ['id' => $scaffold['session']->id]);
    }

    public function test_the_taking_screen_does_not_n_plus_one_for_a_full_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 30);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/attendance/{$register->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "the taking screen ran {$queries} queries for 30 students");
    }

    public function test_the_register_list_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        foreach (range(1, 12) as $i) {
            $this->registerFor($school, $scaffold, ['attendance_date' => now()->subDays($i)->toDateString()]);
        }
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/attendance')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "the register list ran {$queries} queries for 12 registers");
    }

    public function test_bulk_save_does_not_run_a_query_per_student_for_reads(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 30);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $marks = [];
        foreach ($students as $s) {
            $marks[$s->id] = ['status' => 'present'];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->patch("/attendance/{$register->id}/records", ['records' => $marks])->assertSessionHasNoErrors();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // One write per changed student is expected; the existing records are
        // loaded in a single query, never one SELECT per student.
        $this->assertLessThan(30 + 20, $queries, "bulk save ran {$queries} queries for 30 students");
    }
}
