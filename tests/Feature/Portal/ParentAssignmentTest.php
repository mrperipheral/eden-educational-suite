<?php

namespace Tests\Feature\Portal;

use App\Models\Assignment;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Assignment visibility for the Parent Portal — only the child's own
 * submission, and only for issued (published/closed) work, never a draft
 * (see `docs/parent-portal.md` §"Assignments").
 */
class ParentAssignmentTest extends ParentPortalTestCase
{
    private function assignmentFor(School $school, array $scaffold, Collection $students, string $title): Assignment
    {
        $this->enterSchool($school);
        $admin = $this->adminUser($school);

        $assignment = Assignment::create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'title' => $title,
            'assigned_on' => now()->subDays(5)->toDateString(),
            'due_on' => now()->addDays(2)->toDateString(),
        ]);
        $assignment->created_by = $admin->id;
        $assignment->save();
        $students->each(fn ($s) => $assignment->submissions()->create(['student_id' => $s->id]));

        $this->app->forgetScopedInstances();

        return $assignment;
    }

    public function test_a_published_assignment_is_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $assignment = $this->assignmentFor($school, $scaffold, $students, 'Fractions worksheet');
        $this->enterSchool($school);
        $assignment->publish();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/assignments")
            ->assertOk()
            ->assertSee('Fractions worksheet');
    }

    public function test_a_draft_assignment_is_not_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $this->assignmentFor($school, $scaffold, $students, 'Draft reading log');

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/assignments")
            ->assertOk()
            ->assertDontSee('Draft reading log');
    }

    public function test_a_closed_assignment_stays_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $assignment = $this->assignmentFor($school, $scaffold, $students, 'Closed essay');
        $this->enterSchool($school);
        $assignment->publish();
        $assignment->close();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/assignments")
            ->assertOk()
            ->assertSee('Closed essay');
    }

    public function test_another_students_submission_never_leaks_into_this_childs_page(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $this->enterSchool($school);
        $classmate = Student::factory()->create();
        $classmate->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id, 'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id, 'status' => 'active', 'started_on' => $scaffold['session']->starts_on,
        ]);
        $this->app->forgetScopedInstances();

        $assignment = $this->assignmentFor($school, $scaffold, $students->concat([$classmate]), 'Group task');
        $this->enterSchool($school);
        $assignment->publish();
        $classmate->fresh(); // no-op, keeps intent explicit
        $assignment->submissions()->where('student_id', $classmate->id)->update(['remark' => 'Classmate only remark']);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $response = $this->get("/parent/children/{$students[0]->id}/assignments")->assertOk();
        $response->assertSee('Group task');
        $response->assertDontSee('Classmate only remark');
    }

    public function test_assignments_degrade_gracefully_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'assessments', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/assignments")
            ->assertOk()
            ->assertSee(__('Assignments are not currently available'));
    }
}
