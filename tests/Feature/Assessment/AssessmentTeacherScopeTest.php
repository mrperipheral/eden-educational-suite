<?php

namespace Tests\Feature\Assessment;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\Assessment;
use App\Models\Subject;

class AssessmentTeacherScopeTest extends AssessmentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Assessment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_a_teacher_can_record_for_their_assigned_class_and_subject(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsTeacherFor($school, $scaffold);

        $this->post('/assessments', $this->assessmentPayload($scaffold))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_cannot_record_for_another_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);

        // Another class, same subject, with students — teacher not assigned there.
        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 2]);
        $otherScaffold = [...$scaffold, 'arm' => $otherArm];
        $this->app->forgetScopedInstances();
        $this->enrolledStudents($school, $otherScaffold, 2);

        $this->actingAsTeacherFor($school, $scaffold);   // assigned to Gold only

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($otherScaffold))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_cannot_record_for_another_subject_in_their_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);

        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $scaffold['level']->subjects()->attach($otherSubject->id, ['school_id' => $school->id]);
        $otherScaffold = [...$scaffold, 'subject' => $otherSubject];
        $this->app->forgetScopedInstances();

        $this->actingAsTeacherFor($school, $scaffold);   // assigned for the scaffold subject only

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($otherScaffold))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_with_no_linked_teacher_record_cannot_record(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::Teacher);   // role only, no Teacher record

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_assignment_in_another_school_does_not_grant_access(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $this->enrolledStudents($b, $scaffoldB);

        $teacher = $this->actingAsTeacherFor($a, $scaffoldA);
        $teacher->joinSchool($b, Role::Teacher);

        $this->actingAs($teacher);
        $this->withSession([EnforceTenant::SESSION_KEY => $b->getKey()]);

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffoldB))->assertForbidden();
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_a_teacher_cannot_score_an_assessment_for_a_class_they_are_not_assigned_to(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);

        // A teacher assigned to a *different* subject.
        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $scaffold['level']->subjects()->attach($otherSubject->id, ['school_id' => $school->id]);
        $this->app->forgetScopedInstances();
        $this->actingAsTeacherFor($school, [...$scaffold, 'subject' => $otherSubject]);

        $this->get("/assessments/{$assessment->id}/scores")->assertForbidden();
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 5]]])->assertForbidden();
    }
}
