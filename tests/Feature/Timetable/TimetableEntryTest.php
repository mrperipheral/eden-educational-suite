<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Enums\Weekday;
use App\Models\AcademicLevel;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class TimetableEntryTest extends TimetableTestCase
{
    private function rowsFor(int $schoolId)
    {
        return TimetableEntry::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_schedule_a_valid_lesson(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['room' => 'Room 1']))
            ->assertRedirect(route('timetables.show', $timetable->id));

        $entry = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($timetable->id, $entry->timetable_id);
        $this->assertSame($school->id, $entry->school_id);
        $this->assertSame(Weekday::Monday, $entry->weekday);
        $this->assertSame('08:00', $entry->start_time);
        $this->assertSame('Room 1', $entry->room);
    }

    public function test_the_end_time_must_be_after_the_start_time(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        foreach (['08:00', '07:30'] as $end) {
            $this->from(route('timetables.entries.create', $timetable->id))
                ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['end_time' => $end]))
                ->assertSessionHasErrors('end_time');
        }

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_cannot_be_double_booked_for_overlapping_lessons(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        // A second class + arm for the same teacher/subject/level so only the
        // teacher clashes (not the class).
        $this->enterSchool($school);
        $arm2 = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['start_time' => '08:00', 'end_time' => '09:00']));

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, [
                'level_arm_id' => $arm2->id, 'start_time' => '08:30', 'end_time' => '09:30',
            ]))->assertSessionHasErrors('teacher_id');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_a_class_cannot_be_double_booked_for_overlapping_lessons(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        // A second teacher + subject for the same class so only the class clashes.
        $this->enterSchool($school);
        $subject2 = Subject::factory()->create();
        $scaffold['level']->subjects()->attach($subject2->id, ['school_id' => $school->id]);
        $teacher2 = Teacher::factory()->create();
        $teacher2->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $subject2->id,
            'started_on' => '2025-09-15',
        ]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['start_time' => '08:00', 'end_time' => '09:00']));

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, [
                'subject_id' => $subject2->id, 'teacher_id' => $teacher2->id, 'start_time' => '08:45', 'end_time' => '09:45',
            ]))->assertSessionHasErrors('level_arm_id');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_a_room_cannot_be_double_booked_for_overlapping_lessons(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $arm2 = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $subject2 = Subject::factory()->create();
        $scaffold['level']->subjects()->attach($subject2->id, ['school_id' => $school->id]);
        $teacher2 = Teacher::factory()->create();
        $teacher2->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $subject2->id,
            'started_on' => '2025-09-15',
        ]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['room' => 'Hall', 'start_time' => '08:00', 'end_time' => '09:00']));

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, [
                'level_arm_id' => $arm2->id, 'subject_id' => $subject2->id, 'teacher_id' => $teacher2->id,
                'room' => 'Hall', 'start_time' => '08:30', 'end_time' => '09:30',
            ]))->assertSessionHasErrors('room');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_back_to_back_lessons_are_allowed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['start_time' => '08:00', 'end_time' => '09:00', 'room' => 'R1']))
            ->assertSessionHasNoErrors();
        // Same teacher, class, room — but starts exactly when the first ends.
        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['start_time' => '09:00', 'end_time' => '10:00', 'room' => 'R1']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_lessons_on_different_days_do_not_clash(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['weekday' => Weekday::Monday->value]))->assertSessionHasNoErrors();
        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['weekday' => Weekday::Tuesday->value]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_a_subject_not_offered_by_the_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $unofferedSubject = Subject::factory()->create();   // not attached to the level
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['subject_id' => $unofferedSubject->id]))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_without_an_active_assignment_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $unassignedTeacher = Teacher::factory()->create();   // no assignment at all
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['teacher_id' => $unassignedTeacher->id]))
            ->assertSessionHasErrors('teacher_id');

        // And an ended assignment does not count.
        $this->enterSchool($school);
        $endedTeacher = Teacher::factory()->create();
        $endedTeacher->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'status' => 'ended',
            'started_on' => '2024-09-15', 'ended_on' => '2025-07-24',
        ]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['teacher_id' => $endedTeacher->id]))
            ->assertSessionHasErrors('teacher_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_an_arm_from_another_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.entries.create', $timetable->id))
            ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');
    }

    public function test_cross_school_ids_are_rejected_without_leaking(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $timetableB = $this->timetableFor($b, $scaffoldB);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from(route('timetables.entries.create', $timetableB->id))
            ->post("/timetables/{$timetableB->id}/entries", [
                'academic_level_id' => $scaffoldA['level']->id,
                'level_arm_id' => $scaffoldA['arm']->id,
                'subject_id' => $scaffoldA['subject']->id,
                'teacher_id' => $scaffoldA['teacher']->id,
                'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00',
            ])
            ->assertSessionHasErrors(['academic_level_id', 'level_arm_id', 'subject_id', 'teacher_id']);

        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_entries_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $timetableA = $this->timetableFor($a, $scaffoldA);
        $this->enterSchool($a);
        $entryA = $timetableA->entries()->create($this->entryPayload($scaffoldA));
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get("/timetables/entries/{$entryA->id}/edit")->assertNotFound();
        $this->patch("/timetables/entries/{$entryA->id}", $this->entryPayload($scaffoldA))->assertNotFound();
        $this->delete("/timetables/entries/{$entryA->id}")->assertNotFound();
        $this->post("/timetables/{$timetableA->id}/entries", $this->entryPayload($scaffoldA))->assertNotFound();

        $this->assertSame(1, $this->rowsFor($a->id)->count());
    }

    public function test_entry_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $timetableA = $this->timetableFor($a, $scaffoldA);
        $this->enterSchool($a);
        $entry = $timetableA->entries()->create($this->entryPayload($scaffoldA));

        $this->expectException(TenantMismatchException::class);
        $entry->school_id = $b->id;
        $entry->save();
    }

    public function test_an_entry_can_be_edited_and_removed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $entry = $timetable->entries()->create($this->entryPayload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/timetables/entries/{$entry->id}", $this->entryPayload($scaffold, ['start_time' => '10:00', 'end_time' => '11:00', 'room' => 'Lab']))
            ->assertRedirect(route('timetables.show', $timetable->id));
        $this->assertSame('10:00', $entry->fresh()->start_time);
        $this->assertSame('Lab', $entry->fresh()->room);

        $this->from(route('timetables.show', $timetable->id))->delete("/timetables/entries/{$entry->id}")->assertRedirect();
        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_editing_an_entry_does_not_clash_with_itself(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $entry = $timetable->entries()->create($this->entryPayload($scaffold, ['room' => 'R1']));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/timetables/entries/{$entry->id}", $this->entryPayload($scaffold, ['room' => 'R1', 'end_time' => '09:30']))
            ->assertSessionHasNoErrors();
    }

    public function test_view_roles_cannot_manage_entries_and_the_module_gate_applies(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);

        foreach ($this->timetableRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/timetables/{$timetable->id}/entries/create")->assertForbidden();
            $this->from(route('timetables.show', $timetable->id))
                ->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold))->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }
}
