<?php

namespace Tests\Feature\Cbt;

use App\Enums\QuestionStatus;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Services\Cbt\ExaminationQuestionService;

class QuestionLifecycleTest extends CbtTestCase
{
    public function test_deactivating_an_active_question_via_http(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$question->id}/deactivate")->assertRedirect();

        $this->assertSame(QuestionStatus::Inactive, $question->fresh()->status);
    }

    public function test_reactivating_an_inactive_question_via_http(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject'], overrides: ['status' => 'inactive']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$question->id}/activate")->assertRedirect();

        $this->assertSame(QuestionStatus::Active, $question->fresh()->status);
    }

    public function test_archiving_a_question_via_http(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$question->id}/archive")->assertRedirect();

        $this->assertSame(QuestionStatus::Archived, $question->fresh()->status);
    }

    public function test_an_archived_question_can_be_reactivated(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject'], overrides: ['status' => 'archived']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$question->id}/activate")->assertRedirect();

        $this->assertSame(QuestionStatus::Active, $question->fresh()->status);
    }

    public function test_an_inactive_question_is_not_offered_in_the_exam_attach_picker(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $this->questionIn($school, $context['subject'], overrides: ['status' => 'inactive']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/questions/create")->assertOk()->assertDontSee('Sample question?');
    }

    public function test_an_archived_question_is_not_offered_in_the_exam_attach_picker(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $this->questionIn($school, $context['subject'], overrides: ['status' => 'archived']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/questions/create")->assertOk()->assertDontSee('Sample question?');
    }

    public function test_an_active_question_is_offered_in_the_exam_attach_picker(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/questions/create")->assertOk()->assertSee('Sample question?');
    }

    public function test_an_inactive_question_cannot_be_newly_attached_even_via_a_direct_request(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject'], overrides: ['status' => 'inactive']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])
            ->assertStatus(422);

        $this->assertSame(0, $exam->questions()->count());
    }

    public function test_an_archived_question_cannot_be_newly_attached_even_via_a_direct_request(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject'], overrides: ['status' => 'archived']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])
            ->assertStatus(422);

        $this->assertSame(0, $exam->questions()->count());
    }

    public function test_the_service_layer_itself_rejects_attaching_a_non_selectable_question(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject'], overrides: ['status' => 'archived']);

        $this->enterSchool($school);
        $this->expectException(\RuntimeException::class);
        app(ExaminationQuestionService::class)->attach($exam, $question);
    }

    public function test_a_question_scoped_to_a_different_level_is_not_compatible_with_the_exam(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();

        $question = $this->questionIn($school, $context['subject'], overrides: ['academic_level_id' => $otherLevel->id]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/questions/create")->assertOk()->assertDontSee('Sample question?');
        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])->assertStatus(422);
    }

    public function test_a_level_agnostic_question_is_compatible_with_any_matching_subject_exam(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']); // no level set — reusable anywhere
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])
            ->assertRedirect(route('cbt.examinations.show', $exam->id));

        $this->assertSame(1, $exam->questions()->count());
    }
}
