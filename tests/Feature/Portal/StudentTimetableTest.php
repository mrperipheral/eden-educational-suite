<?php

namespace Tests\Feature\Portal;

use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Teacher;
use App\Models\Timetable;

/**
 * Timetable visibility for the Student Portal — the student's *current*
 * class, published only (see `docs/student-portal.md` §"Timetable").
 */
class StudentTimetableTest extends StudentPortalTestCase
{
    private function enableTimetableModule(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'timetable', 'enabled' => true]);
        $this->app->forgetScopedInstances();
    }

    public function test_a_published_timetable_for_the_students_class_is_visible(): void
    {
        $school = $this->newSchool();
        $this->enableTimetableModule($school);
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));

        $this->enterSchool($school);
        $teacher = Teacher::factory()->create();
        $timetable = Timetable::create(['academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'name' => 'First Term']);
        $timetable->entries()->create([
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id, 'teacher_id' => $teacher->id,
            'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'room' => 'Room 1',
        ]);
        $timetable->publish();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/timetable')->assertOk()->assertSee($scaffold['subject']->name);
    }

    public function test_a_draft_timetable_is_not_visible(): void
    {
        $school = $this->newSchool();
        $this->enableTimetableModule($school);
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));

        $this->enterSchool($school);
        $teacher = Teacher::factory()->create();
        $timetable = Timetable::create(['academic_session_id' => $scaffold['session']->id, 'name' => 'Draft Timetable']);
        $timetable->entries()->create([
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id, 'teacher_id' => $teacher->id,
            'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00',
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/timetable')
            ->assertOk()
            ->assertSee(__('No published timetable is currently available'));
    }

    public function test_timetable_degrades_gracefully_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/timetable')
            ->assertOk()
            ->assertSee(__('Timetable is not currently available'));
    }
}
