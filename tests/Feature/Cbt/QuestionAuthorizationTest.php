<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\Subject;

class QuestionAuthorizationTest extends CbtTestCase
{
    private function payload(Subject $subject, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subject->id,
            'question_text' => 'What is 2 + 2?',
            'type' => 'multiple_choice',
            'difficulty' => 'medium',
            'marks' => 1,
            'options' => [
                ['option_text' => '3', 'is_correct' => 0],
                ['option_text' => '4', 'is_correct' => 1],
            ],
        ], $overrides);
    }

    public function test_school_admin_and_principal_can_create_for_any_subject(): void
    {
        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $school = $this->newSchool();
            $this->enableCbt($school);
            $this->enterSchool($school);
            $subject = Subject::factory()->create();
            $this->app->forgetScopedInstances();
            $this->actingAsRole($school, $role);

            $this->post('/cbt/questions', $this->payload($subject))->assertRedirect(route('cbt.questions.index'));
        }
    }

    public function test_teacher_can_create_a_level_agnostic_question_for_their_own_subject(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->post('/cbt/questions', $this->payload($context['subject']))
            ->assertRedirect(route('cbt.questions.index'));
    }

    public function test_teacher_can_create_a_question_for_their_own_class(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->post('/cbt/questions', $this->payload($context['subject'], [
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
        ]))->assertRedirect(route('cbt.questions.index'));
    }

    public function test_teacher_cannot_create_a_question_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $this->app->forgetScopedInstances();

        $this->post('/cbt/questions', $this->payload($otherSubject))->assertForbidden();
    }

    public function test_teacher_cannot_create_a_question_for_a_level_they_do_not_teach(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherLevel->subjects()->attach($context['subject']->id, ['school_id' => $school->id]);
        $this->app->forgetScopedInstances();

        $this->post('/cbt/questions', $this->payload($context['subject'], [
            'academic_level_id' => $otherLevel->id,
        ]))->assertForbidden();
    }

    public function test_teacher_cannot_edit_a_question_outside_their_scope(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $otherContext = $this->classContext($school);
        $question = $this->questionIn($school, $otherContext['subject'], overrides: [
            'academic_level_id' => $otherContext['level']->id,
            'level_arm_id' => $otherContext['arm']->id,
        ]);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->get("/cbt/questions/{$question->id}/edit")->assertForbidden();
        $this->patch("/cbt/questions/{$question->id}", $this->payload($otherContext['subject']))->assertForbidden();
        $this->post("/cbt/questions/{$question->id}/archive")->assertForbidden();
    }

    public function test_teacher_can_edit_their_own_scoped_question(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject'], overrides: [
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
        ]);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->get("/cbt/questions/{$question->id}/edit")->assertOk();
        $this->post("/cbt/questions/{$question->id}/archive")->assertRedirect();

        $this->assertSame('archived', $question->fresh()->status->value);
    }

    public function test_teacher_cannot_move_a_question_into_a_subject_they_do_not_teach_via_update(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject']);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $this->app->forgetScopedInstances();

        $this->patch("/cbt/questions/{$question->id}", $this->payload($otherSubject))->assertForbidden();
    }

    public function test_bursar_and_parent_have_no_question_bank_access(): void
    {
        foreach ([Role::Bursar, Role::Parent, Role::Student] as $role) {
            $school = $this->newSchool();
            $this->enableCbt($school);
            $this->actingAsRole($school, $role);

            $this->get('/cbt/questions')->assertForbidden();
        }
    }

    public function test_a_school_cannot_preview_another_schools_question(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $questionB = $this->questionIn($schoolB, $contextB['subject']);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/cbt/questions/{$questionB->id}/preview")->assertNotFound();
    }

    public function test_a_school_cannot_edit_another_schools_question(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextA = $this->classContext($schoolA);
        $contextB = $this->classContext($schoolB);
        $questionB = $this->questionIn($schoolB, $contextB['subject']);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        // Uses School A's own subject id — proves the {question} route
        // parameter's tenant-scoped findOrFail is what blocks this, not
        // just the subject_id validation rule rejecting a foreign id.
        $this->get("/cbt/questions/{$questionB->id}/edit")->assertNotFound();
        $this->patch("/cbt/questions/{$questionB->id}", $this->payload($contextA['subject']))->assertNotFound();
    }

    public function test_a_school_cannot_archive_another_schools_question(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $questionB = $this->questionIn($schoolB, $contextB['subject']);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$questionB->id}/archive")->assertNotFound();

        $this->assertSame('active', $questionB->fresh()->status->value);
    }

    public function test_a_school_cannot_see_another_schools_question_in_the_bank_index(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $this->questionIn($schoolB, $contextB['subject'], overrides: ['topic' => 'UniqueTopicMarkerForSchoolB']);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get('/cbt/questions')->assertOk()->assertDontSee('UniqueTopicMarkerForSchoolB');
    }
}
