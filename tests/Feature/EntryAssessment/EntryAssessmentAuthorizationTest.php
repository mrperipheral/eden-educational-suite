<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\Subject;

/**
 * Role-based authorization, Teacher class/subject scoping, cross-school
 * tenant isolation and IDOR protection (M25,
 * `docs/entry-placement-assessment.md`).
 */
class EntryAssessmentAuthorizationTest extends EntryAssessmentTestCase
{
    public function test_school_admin_has_full_access(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->get('/entry-assessments')->assertOk();
        $this->post('/entry-assessments', $this->payload($context))->assertRedirect(route('entry-assessments.index'));
    }

    public function test_principal_has_full_access(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Principal);
        $context = $this->classContext($school);

        $this->get('/entry-assessments')->assertOk();
        $this->post('/entry-assessments', $this->payload($context))->assertRedirect(route('entry-assessments.index'));
    }

    public function test_teacher_can_record_for_their_own_assigned_class(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->post('/entry-assessments', $this->payload($context))->assertRedirect(route('entry-assessments.index'));
    }

    public function test_teacher_with_an_arm_agnostic_assignment_can_record_for_any_arm(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context); // no arm — arm-agnostic

        $this->post('/entry-assessments', $this->payload($context))->assertRedirect(route('entry-assessments.index'));
    }

    public function test_teacher_cannot_record_for_a_subject_they_do_not_teach(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $context['level']->subjects()->attach($otherSubject->id, ['school_id' => $school->id]);
        $this->app->forgetScopedInstances();

        $this->post('/entry-assessments', $this->payload($context, ['subject_id' => $otherSubject->id]))
            ->assertForbidden();
    }

    public function test_teacher_cannot_record_for_a_level_they_do_not_teach(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherLevel->subjects()->attach($context['subject']->id, ['school_id' => $school->id]);
        $this->app->forgetScopedInstances();

        $this->post('/entry-assessments', $this->payload($context, ['academic_level_id' => $otherLevel->id, 'level_arm_id' => null]))
            ->assertForbidden();
    }

    public function test_teacher_cannot_edit_a_record_outside_their_scope(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $adminContext = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $adminContext);

        $teacherContext = $this->classContext($school);
        $this->teacherAssignedTo($school, $teacherContext, $teacherContext['arm']);

        $this->get("/entry-assessments/{$assessment->id}/edit")->assertForbidden();
        $this->patch("/entry-assessments/{$assessment->id}", $this->payload($adminContext))->assertForbidden();
        $this->post("/entry-assessments/{$assessment->id}/archive")->assertForbidden();
    }

    public function test_teacher_can_edit_a_record_within_their_scope(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $teacher = $this->teacherAssignedTo($school, $context, $context['arm']);
        $assessment = $this->assessmentIn($school, $context, ['assessor_id' => $teacher->id]);

        $this->get("/entry-assessments/{$assessment->id}/edit")->assertOk();
        $this->patch("/entry-assessments/{$assessment->id}", $this->payload($context, ['candidate_name' => 'Updated']))
            ->assertRedirect(route('entry-assessments.index'));
    }

    public function test_bursar_is_forbidden(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Bursar);
        $context = $this->classContext($school);

        $this->get('/entry-assessments')->assertForbidden();
        $this->post('/entry-assessments', $this->payload($context))->assertForbidden();
    }

    public function test_parent_is_forbidden(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Parent);

        $this->get('/entry-assessments')->assertForbidden();
    }

    public function test_student_is_forbidden(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Student);

        $this->get('/entry-assessments')->assertForbidden();
    }

    public function test_staff_can_view_but_not_record(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Staff);
        $context = $this->classContext($school);

        $this->get('/entry-assessments')->assertOk();
        $this->get('/entry-assessments/create')->assertForbidden();
        $this->post('/entry-assessments', $this->payload($context))->assertForbidden();
    }

    public function test_role_less_member_is_forbidden(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, null);

        $this->get('/entry-assessments')->assertForbidden();
    }

    public function test_module_off_returns_404(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/entry-assessments')->assertNotFound();
    }

    public function test_a_school_cannot_view_another_schools_record(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->enableEntryAssessment($schoolB);
        $contextB = $this->classContext($schoolB);
        $assessment = $this->assessmentIn($schoolB, $contextB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/entry-assessments/{$assessment->id}")->assertNotFound();
    }

    public function test_a_school_cannot_edit_another_schools_record(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->enableEntryAssessment($schoolB);
        $contextB = $this->classContext($schoolB);
        $assessment = $this->assessmentIn($schoolB, $contextB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);
        $contextA = $this->classContext($schoolA);

        $this->get("/entry-assessments/{$assessment->id}/edit")->assertNotFound();
        $this->patch("/entry-assessments/{$assessment->id}", $this->payload($contextA))->assertNotFound();
    }

    public function test_a_school_cannot_archive_another_schools_record(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->enableEntryAssessment($schoolB);
        $contextB = $this->classContext($schoolB);
        $assessment = $this->assessmentIn($schoolB, $contextB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->post("/entry-assessments/{$assessment->id}/archive")->assertNotFound();
    }

    public function test_a_school_never_sees_another_schools_records_in_its_index(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->enableEntryAssessment($schoolB);
        $contextB = $this->classContext($schoolB);
        $this->assessmentIn($schoolB, $contextB, ['candidate_name' => 'Foreign Candidate']);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get('/entry-assessments')->assertOk()->assertDontSee('Foreign Candidate');
    }

    public function test_school_id_from_the_request_is_ignored(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);
        $context = $this->classContext($schoolA);

        $this->post('/entry-assessments', array_merge($this->payload($context), ['school_id' => $schoolB->id]))
            ->assertRedirect(route('entry-assessments.index'));

        $this->enterSchool($schoolA);
        $this->assertDatabaseHas('entry_assessments', ['school_id' => $schoolA->id]);
        $this->assertDatabaseMissing('entry_assessments', ['school_id' => $schoolB->id]);
    }
}
