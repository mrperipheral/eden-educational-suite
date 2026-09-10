<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Student;
use Illuminate\Database\QueryException;

class AttendanceRecordTest extends AttendanceTestCase
{
    /** @return array<string, mixed> */
    private function marks(iterable $students, string|array $status): array
    {
        $out = [];
        foreach ($students as $i => $student) {
            $s = is_array($status) ? $status[$i % count($status)] : $status;
            $out[$student->id] = ['status' => $s];
        }

        return $out;
    }

    public function test_students_can_be_marked_present_absent_late_and_excused(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 4);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", [
            'records' => $this->marks($students->values(), ['present', 'absent', 'late', 'excused']),
        ])->assertRedirect(route('attendance.show', $register->id));

        $this->assertSame(1, $register->records()->where('status', 'present')->count());
        $this->assertSame(1, $register->records()->where('status', 'absent')->count());
        $this->assertSame(1, $register->records()->where('status', 'late')->count());
        $this->assertSame(1, $register->records()->where('status', 'excused')->count());
        $this->assertNotNull($register->records()->whereNotNull('recorded_at')->first());
    }

    public function test_a_note_can_be_recorded_and_is_length_limited(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", [
            'records' => [$student->id => ['status' => 'excused', 'note' => 'Dental appointment']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Dental appointment', $register->records()->where('student_id', $student->id)->value('note'));

        $this->from(route('attendance.show', $register->id))
            ->patch("/attendance/{$register->id}/records", [
                'records' => [$student->id => ['status' => 'excused', 'note' => str_repeat('x', 300)]],
            ])->assertSessionHasErrors('records.'.$student->id.'.note');
    }

    public function test_marks_can_be_corrected_before_the_register_is_locked(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", ['records' => [$student->id => ['status' => 'absent']]]);
        $this->patch("/attendance/{$register->id}/records", ['records' => [$student->id => ['status' => 'present']]]);

        $this->assertSame(AttendanceStatus::Present, $register->records()->where('student_id', $student->id)->value('status'));
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('attendance.show', $register->id))
            ->patch("/attendance/{$register->id}/records", ['records' => [$student->id => ['status' => 'truant']]])
            ->assertSessionHasErrors('records.'.$student->id.'.status');
    }

    public function test_a_student_not_on_the_register_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->enterSchool($school);
        $strangerStudent = Student::factory()->create();   // same school, not in this class
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('attendance.show', $register->id))
            ->patch("/attendance/{$register->id}/records", ['records' => [$strangerStudent->id => ['status' => 'present']]])
            ->assertSessionHasErrors('records');

        $this->assertSame(0, $register->records()->where('student_id', $strangerStudent->id)->count());
    }

    public function test_the_register_prevents_duplicate_student_rows(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $register = $this->registerFor($school, $scaffold);
        $this->enterSchool($school);
        $register->records()->create(['student_id' => $student->id, 'status' => 'present']);

        $this->expectException(QueryException::class);
        $register->records()->create(['student_id' => $student->id, 'status' => 'absent']);
    }

    public function test_a_full_class_can_be_marked_in_one_request(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 10);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", [
            'records' => $this->marks($students->values(), 'present'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $register->records()->where('status', 'present')->count());
        $this->assertSame(0, $register->records()->whereNull('status')->count());
    }
}
