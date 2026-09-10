<?php

namespace Tests\Feature\Assessment;

use App\Enums\Role;
use App\Models\Assessment;

class AssessmentAuthorizationTest extends AssessmentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Assessment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_managers_can_create_an_assessment_for_any_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);

        $day = 1;
        foreach ($this->assessmentRoles()['manage'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->post('/assessments', $this->assessmentPayload($scaffold, [
                'assessment_date' => now()->subDays($day++)->toDateString(),
            ]))->assertSessionHasNoErrors();
            $this->flushSession();
        }

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_view_only_roles_can_read_but_not_create_or_record(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);

        foreach ($this->assessmentRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/assessments')->assertOk();
            $this->get("/assessments/{$assessment->id}")->assertOk();
            $this->get('/assessments/categories')->assertOk();
            $this->get('/assessments/create')->assertForbidden();
            $this->from('/assessments')->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_date' => now()->subDays(3)->toDateString()]))->assertForbidden();
            $this->get("/assessments/{$assessment->id}/scores")->assertForbidden();
            $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$students[0]->id => ['score' => 1]]])->assertForbidden();
            $this->post('/assessments/categories', ['name' => 'X'])->assertForbidden();
            $this->post("/assessments/{$assessment->id}/unlock")->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        foreach ($this->assessmentRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/assessments')->assertForbidden();
            $this->get('/assessments/assignments')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_only_managers_can_manage_categories(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        // Teacher has assessment.record but not .manage → cannot touch categories.
        $this->actingAsMemberOf($school, Role::Teacher);
        $this->post('/assessments/categories', ['name' => 'Homework'])->assertForbidden();
        $this->flushSession();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/assessments/categories', ['name' => 'Homework'])->assertRedirect();
        $this->assertDatabaseHas('assessment_categories', ['school_id' => $school->id, 'name' => 'Homework']);
    }

    public function test_routes_are_unavailable_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableAssessments($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/assessments')->assertNotFound();
        $this->get('/assessments/create')->assertNotFound();
        $this->get('/assessments/categories')->assertNotFound();
        $this->get('/assessments/assignments')->assertNotFound();
        $this->post('/assessments', [])->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permission(): void
    {
        // Assessments module on by default; a Bursar still cannot see it.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get('/assessments')->assertForbidden();
    }
}
