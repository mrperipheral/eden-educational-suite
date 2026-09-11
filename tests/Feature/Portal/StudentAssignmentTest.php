<?php

namespace Tests\Feature\Portal;

use App\Models\Assignment;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Assignment visibility for the Student Portal — only the student's own
 * submission, only issued (published/closed) work (see
 * `docs/student-portal.md` §"Assignments").
 */
class StudentAssignmentTest extends StudentPortalTestCase
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
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $assignment = $this->assignmentFor($school, $scaffold, collect([$student]), 'Fractions worksheet');
        $this->enterSchool($school);
        $assignment->publish();
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/assignments')->assertOk()->assertSee('Fractions worksheet');
    }

    public function test_a_draft_assignment_is_not_visible(): void
    {
        $school = $this->newSchool();
        [$user, $student] = $this->studentWithAccount($school);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$student]));
        $this->assignmentFor($school, $scaffold, collect([$student]), 'Draft reading log');

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/assignments')->assertOk()->assertDontSee('Draft reading log');
    }

    public function test_a_classmates_submission_never_leaks(): void
    {
        $school = $this->newSchool();
        [$userA, $studentA] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        $classmate = Student::factory()->create();
        $this->app->forgetScopedInstances();
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, collect([$studentA, $classmate]));

        $assignment = $this->assignmentFor($school, $scaffold, collect([$studentA, $classmate]), 'Group task');
        $this->enterSchool($school);
        $assignment->publish();
        $assignment->submissions()->where('student_id', $classmate->id)->update(['remark' => 'Classmate only remark']);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $userA);
        $response = $this->get('/student/assignments')->assertOk();
        $response->assertSee('Group task');
        $response->assertDontSee('Classmate only remark');
    }

    public function test_assignments_degrade_gracefully_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->studentWithAccount($school);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'assessments', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsStudentUser($school, $user);
        $this->get('/student/assignments')
            ->assertOk()
            ->assertSee(__('Assignments are not currently available'));
    }
}
