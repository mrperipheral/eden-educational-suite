<?php

namespace Tests\Feature\Portal;

use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Teacher;
use App\Models\Timetable;

/**
 * Timetable visibility for the Parent Portal — the child's *current* class,
 * published only, degrading cleanly when unavailable (see
 * `docs/parent-portal.md` §"Timetable").
 */
class ParentTimetableTest extends ParentPortalTestCase
{
    private function enableTimetableModule(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'timetable', 'enabled' => true]);
        $this->app->forgetScopedInstances();
    }

    public function test_a_published_timetable_for_the_childs_class_is_visible(): void
    {
        $school = $this->newSchool();
        $this->enableTimetableModule($school);
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);

        $this->enterSchool($school);
        $admin = $this->adminUser($school);
        $teacher = Teacher::factory()->create();
        $timetable = Timetable::create(['academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'name' => 'First Term']);
        $timetable->entries()->create([
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id, 'teacher_id' => $teacher->id,
            'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'room' => 'Room 1',
        ]);
        $timetable->publish();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/timetable")
            ->assertOk()
            ->assertSee($scaffold['subject']->name);
    }

    public function test_a_draft_timetable_is_not_visible(): void
    {
        $school = $this->newSchool();
        $this->enableTimetableModule($school);
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);

        $this->enterSchool($school);
        $teacher = Teacher::factory()->create();
        $timetable = Timetable::create(['academic_session_id' => $scaffold['session']->id, 'name' => 'Draft Timetable']);
        $timetable->entries()->create([
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id, 'teacher_id' => $teacher->id,
            'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00',
        ]);
        // Left as draft — never published.
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/timetable")
            ->assertOk()
            ->assertSee(__('No published timetable is currently available'));
    }

    public function test_a_child_with_no_current_enrollment_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        $this->enableTimetableModule($school);
        [$user, , $students] = $this->parentWithChildren($school, 1);

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/timetable")
            ->assertOk()
            ->assertSee(__('No published timetable is currently available'));
    }

    public function test_timetable_degrades_gracefully_when_the_module_is_off(): void
    {
        // Timetable defaults to off — no need to explicitly disable it.
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/timetable")
            ->assertOk()
            ->assertSee(__('Timetable is not currently available'));
    }
}
