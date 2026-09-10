<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;

class AttendanceLifecycleTest extends AttendanceTestCase
{
    /** @return array<string, mixed> */
    private function allPresent(iterable $students): array
    {
        $out = [];
        foreach ($students as $s) {
            $out[$s->id] = ['status' => 'present'];
        }

        return $out;
    }

    public function test_a_fully_marked_register_can_be_submitted_and_locked(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", ['records' => $this->allPresent($students)]);
        $this->post("/attendance/{$register->id}/submit")->assertRedirect(route('attendance.show', $register->id));

        $fresh = $register->fresh();
        $this->assertSame(AttendanceRegisterStatus::Submitted, $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame($admin->id, $fresh->submitted_by);
    }

    public function test_a_register_with_unmarked_students_cannot_be_submitted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // Only two of three marked.
        $this->patch("/attendance/{$register->id}/records", ['records' => [
            $students[0]->id => ['status' => 'present'],
            $students[1]->id => ['status' => 'absent'],
        ]]);

        $this->from(route('attendance.show', $register->id))
            ->post("/attendance/{$register->id}/submit")
            ->assertSessionHasErrors('register');

        $this->assertSame(AttendanceRegisterStatus::Draft, $register->fresh()->status);
    }

    public function test_save_and_submit_in_one_request_still_needs_every_student_marked(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // One left unmarked in a submit=1 request → saved but not locked.
        $this->from(route('attendance.show', $register->id))
            ->patch("/attendance/{$register->id}/records", [
                'records' => [$students[0]->id => ['status' => 'present']],
                'submit' => '1',
            ])->assertSessionHas('error');
        $this->assertSame(AttendanceRegisterStatus::Draft, $register->fresh()->status);

        // Now all marked → save & submit locks it.
        $this->patch("/attendance/{$register->id}/records", [
            'records' => $this->allPresent($students),
            'submit' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRegisterStatus::Submitted, $register->fresh()->status);
    }

    public function test_a_locked_register_rejects_further_edits_from_a_normal_user(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold, ['status' => 'submitted', 'submitted_at' => now()]);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/attendance/{$register->id}/records", ['records' => $this->allPresent($students)])->assertForbidden();
        $this->post("/attendance/{$register->id}/submit")->assertForbidden();
        $this->delete("/attendance/{$register->id}")
            ->assertRedirect(route('attendance.show', $register->id))
            ->assertSessionHas('error');

        $this->assertSame(0, $register->records()->where('status', 'present')->count());
    }

    public function test_only_an_attendance_manager_can_reopen_a_locked_register(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold, ['status' => 'submitted', 'submitted_at' => now()]);
        $this->snapshotRoster($school, $register);

        // A teacher (record, not manage) cannot reopen.
        $this->actingAsTeacherFor($school, $scaffold);
        $this->post("/attendance/{$register->id}/reopen")->assertForbidden();
        $this->assertSame(AttendanceRegisterStatus::Submitted, $register->fresh()->status);

        // The principal can — then corrections are possible again.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Principal);
        $this->post("/attendance/{$register->id}/reopen")->assertRedirect(route('attendance.show', $register->id));
        $fresh = $register->fresh();
        $this->assertSame(AttendanceRegisterStatus::Draft, $fresh->status);
        $this->assertNull($fresh->submitted_at);

        $this->patch("/attendance/{$register->id}/records", ['records' => $this->allPresent($students)])->assertSessionHasNoErrors();
    }

    public function test_a_draft_register_can_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $register = $this->registerFor($school, $scaffold);
        $this->snapshotRoster($school, $register);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/attendance/{$register->id}")->assertRedirect(route('attendance.index'));

        $this->assertNull($register->fresh());
        $this->assertSame(0, AttendanceRecord::query()->withoutGlobalScopes()->where('attendance_register_id', $register->id)->count());
    }
}
