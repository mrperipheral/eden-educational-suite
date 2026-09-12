<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use App\Models\Question;
use App\Models\Subject;

class QuestionTest extends CbtTestCase
{
    private function payload(Subject $subject, array $overrides = []): array
    {
        return array_merge([
            'subject_id' => $subject->id,
            'question_text' => 'What is 2 + 2?',
            'type' => 'multiple_choice',
            'marks' => 1,
            'is_active' => 1,
            'options' => [
                ['option_text' => '3', 'is_correct' => 0],
                ['option_text' => '4', 'is_correct' => 1],
                ['option_text' => '5', 'is_correct' => 0],
            ],
        ], $overrides);
    }

    public function test_admin_can_create_a_multiple_choice_question(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/cbt/questions', $this->payload($subject))->assertRedirect(route('cbt.questions.index'));

        $question = Question::query()->with('options')->firstOrFail();
        $this->assertSame('multiple_choice', $question->type->value);
        $this->assertCount(3, $question->options);
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
    }

    public function test_true_false_question_requires_exactly_two_options(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/questions', $this->payload($subject, [
            'type' => 'true_false',
            'options' => [
                ['option_text' => 'True', 'is_correct' => 1],
                ['option_text' => 'False', 'is_correct' => 0],
                ['option_text' => 'Maybe', 'is_correct' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('options');
        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_valid_true_false_question_is_accepted(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/cbt/questions', $this->payload($subject, [
            'type' => 'true_false',
            'options' => [
                ['option_text' => 'True', 'is_correct' => 1],
                ['option_text' => 'False', 'is_correct' => 0],
            ],
        ]))->assertRedirect(route('cbt.questions.index'));

        $this->assertSame(1, Question::query()->count());
    }

    public function test_exactly_one_correct_option_is_required_zero_correct(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/questions', $this->payload($subject, [
            'options' => [
                ['option_text' => '3', 'is_correct' => 0],
                ['option_text' => '4', 'is_correct' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('options');
        $this->assertSame(0, Question::query()->count());
    }

    public function test_exactly_one_correct_option_is_required_two_correct(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/questions', $this->payload($subject, [
            'options' => [
                ['option_text' => '3', 'is_correct' => 1],
                ['option_text' => '4', 'is_correct' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('options');
        $this->assertSame(0, Question::query()->count());
    }

    public function test_multiple_choice_requires_at_least_two_options(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->enterSchool($school);
        $subject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/questions', $this->payload($subject, [
            'options' => [
                ['option_text' => 'Only one', 'is_correct' => 1],
            ],
        ]));

        $response->assertSessionHasErrors('options');
    }

    public function test_a_school_cannot_use_another_schools_subject(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $this->enterSchool($schoolB);
        $subjectB = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/cbt/questions', $this->payload($subjectB));

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_editing_a_question_replaces_its_options(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch("/cbt/questions/{$question->id}", $this->payload($context['subject'], [
            'question_text' => 'Updated text?',
            'options' => [
                ['option_text' => 'X', 'is_correct' => 0],
                ['option_text' => 'Y', 'is_correct' => 1],
            ],
        ]))->assertRedirect(route('cbt.questions.index'));

        $question->refresh();
        $this->assertSame('Updated text?', $question->question_text);
        $this->assertCount(2, $question->options()->get());
    }

    public function test_toggle_active_flips_the_flag(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $question = $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/questions/{$question->id}/toggle-active")->assertRedirect();

        $this->assertFalse($question->fresh()->is_active);
    }

    public function test_staff_can_view_but_not_create_questions(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->actingAsRole($school, Role::Staff);

        $this->get('/cbt/questions')->assertOk();
        $this->get('/cbt/questions/create')->assertForbidden();
        $this->post('/cbt/questions', [])->assertForbidden();
    }

    public function test_module_off_returns_404(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/cbt/questions')->assertNotFound();
    }
}
