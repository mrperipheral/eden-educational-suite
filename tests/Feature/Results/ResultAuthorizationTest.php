<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\ResultRun;

class ResultAuthorizationTest extends ResultsTestCase
{
    private function rowsFor(int $schoolId)
    {
        return ResultRun::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_managers_can_create_and_compile_a_run(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        // A result run is unique per class+term, so each manager needs its
        // own class arm within the shared scaffold to create+compile one.
        foreach ($this->resultRoles()['manage'] as $i => $role) {
            $this->enterSchool($school);
            $arm = $i === 0 ? $scaffold['arm'] : $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
            $this->app->forgetScopedInstances();

            $roleScaffold = array_merge($scaffold, ['arm' => $arm]);
            $students = $this->enrolledStudents($school, $roleScaffold, 1);
            $this->lockedAssessment($school, $roleScaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
            $this->lockedAssessment($school, $roleScaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);

            $this->actingAsMemberOf($school, $role);
            $this->post('/results/runs', $this->runPayload($roleScaffold))->assertSessionHasNoErrors();
            $run = $this->rowsFor($school->id)->latest('id')->first();
            $this->post("/results/runs/{$run->id}/compile")->assertSessionHasNoErrors();
            $this->flushSession();
        }
    }

    public function test_teachers_can_view_and_comment_only_for_their_own_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $studentResult = $run->studentResults()->firstOrFail();
        $this->flushSession();

        $this->actingAsTeacherFor($school, $scaffold);
        $this->get('/results/runs')->assertOk();
        $this->get("/results/runs/{$run->id}")->assertOk();
        $this->patch("/results/runs/{$run->id}/students/{$studentResult->id}/comment", [
            'class_teacher_comment' => 'Good effort this term.',
        ])->assertRedirect();
        $this->assertSame('Good effort this term.', $studentResult->fresh()->class_teacher_comment);

        // A teacher cannot compile / manage.
        $this->get('/results/runs/create')->assertForbidden();
        $this->post("/results/runs/{$run->id}/compile")->assertForbidden();
    }

    public function test_a_teacher_not_assigned_to_the_class_cannot_comment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $studentResult = $run->studentResults()->firstOrFail();
        $this->flushSession();

        $this->actingAsMemberOf($school, Role::Teacher);   // no linked Teacher record / assignment
        $this->patch("/results/runs/{$run->id}/students/{$studentResult->id}/comment", [
            'class_teacher_comment' => 'Should not be allowed.',
        ])->assertForbidden();
    }

    public function test_a_teacher_cannot_set_the_principal_comment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $studentResult = $run->studentResults()->firstOrFail();
        $this->flushSession();

        $this->actingAsTeacherFor($school, $scaffold);
        $this->patch("/results/runs/{$run->id}/students/{$studentResult->id}/comment", [
            'principal_comment' => 'Impersonating the principal.',
        ])->assertRedirect();

        $this->assertNull($studentResult->fresh()->principal_comment);
    }

    public function test_view_only_roles_can_read_but_not_write(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $run = $this->resultRun($school, $scaffold);

        foreach ($this->resultRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/results/runs')->assertOk();
            $this->get("/results/runs/{$run->id}")->assertOk();
            $this->get('/results/grading-schemes')->assertOk();
            $this->get('/results/runs/create')->assertForbidden();
            $this->post('/results/runs', $this->runPayload($scaffold))->assertForbidden();
            $this->post("/results/runs/{$run->id}/compile")->assertForbidden();
            $this->post('/results/grading-schemes', ['name' => 'X'])->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        foreach ($this->resultRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/results/runs')->assertForbidden();
            $this->get('/results/grading-schemes')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_routes_are_unavailable_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableResults($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/results/runs')->assertNotFound();
        $this->get('/results/runs/create')->assertNotFound();
        $this->get('/results/grading-schemes')->assertNotFound();
        $this->post('/results/runs', [])->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permission(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get('/results/runs')->assertForbidden();
    }
}
