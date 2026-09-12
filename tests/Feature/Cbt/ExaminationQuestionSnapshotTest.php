<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use App\Models\ExaminationQuestion;
use App\Services\Cbt\ExaminationQuestionService;

class ExaminationQuestionSnapshotTest extends CbtTestCase
{
    public function test_attaching_a_question_snapshots_its_current_content(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);

        $this->assertSame($question->question_text, $examinationQuestion->question_text);
        $this->assertSame($question->type, $examinationQuestion->type);
        $this->assertSame((string) $question->marks, (string) $examinationQuestion->marks);
        $this->assertSame(3, $examinationQuestion->options()->count());
        $this->assertSame(1, $examinationQuestion->options()->where('is_correct', true)->count());
    }

    public function test_editing_the_source_question_never_changes_an_already_attached_snapshot(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);

        $question->question_text = 'Completely different text now';
        $question->save();
        $question->options()->delete();
        $question->options()->create(['option_text' => 'New option', 'is_correct' => true, 'position' => 1]);

        $examinationQuestion->refresh();
        $this->assertNotSame('Completely different text now', $examinationQuestion->question_text);
        $this->assertSame(3, $examinationQuestion->options()->count(), 'the snapshot options are untouched');

        $this->app->forgetScopedInstances();
    }

    public function test_deleting_the_source_question_never_deletes_the_snapshot(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);
        $question->delete();

        $this->assertNotNull($examinationQuestion->fresh());
        $this->assertSame(3, $examinationQuestion->fresh()->options()->count());

        $this->app->forgetScopedInstances();
    }

    public function test_a_question_can_only_be_attached_while_the_exam_is_draft(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'scheduled', 'scheduled_at' => now()]);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $this->expectException(\RuntimeException::class);
        app(ExaminationQuestionService::class)->attach($exam, $question);
    }

    public function test_a_question_can_only_be_detached_while_the_exam_is_draft(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->status = 'scheduled';
        $exam->scheduled_at = now();
        $exam->save();
        $this->app->forgetScopedInstances();

        $this->enterSchool($school);
        $this->expectException(\RuntimeException::class);
        app(ExaminationQuestionService::class)->detach($exam, $examinationQuestion);
    }

    public function test_http_attach_and_detach_flow(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])
            ->assertRedirect(route('cbt.examinations.show', $exam->id));

        $examinationQuestion = ExaminationQuestion::query()->where('examination_id', $exam->id)->firstOrFail();

        $this->delete("/cbt/examinations/{$exam->id}/questions/{$examinationQuestion->id}")
            ->assertRedirect(route('cbt.examinations.show', $exam->id));

        $this->assertSame(0, ExaminationQuestion::query()->where('examination_id', $exam->id)->count());
    }

    public function test_teacher_cannot_attach_a_question_to_a_class_they_do_not_teach(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $otherContext = $this->classContext($school);
        $exam = $this->examinationIn($school, $otherContext);
        $question = $this->questionIn($school, $otherContext['subject']);
        $this->teacherAssignedTo($school, $context, $context['arm']);

        $this->get("/cbt/examinations/{$exam->id}/questions/create")->assertForbidden();
        $this->post("/cbt/examinations/{$exam->id}/questions", ['question_id' => $question->id])->assertForbidden();
    }
}
