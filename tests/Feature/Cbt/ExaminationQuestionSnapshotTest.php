<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use App\Models\ExamAnswer;
use App\Models\ExaminationQuestion;
use App\Services\Cbt\ExamAttemptService;
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

    public function test_archiving_the_source_question_after_attach_leaves_the_snapshot_fully_intact(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);
        $question->archive();

        $this->assertSame('archived', $question->fresh()->status->value);
        $this->assertNotNull($examinationQuestion->fresh());
        $this->assertSame($question->question_text, $examinationQuestion->fresh()->question_text);
        $this->assertSame(3, $examinationQuestion->fresh()->options()->count());

        $this->app->forgetScopedInstances();
    }

    public function test_an_exam_with_an_archived_source_question_still_schedules_and_can_be_attempted(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, [
            'status' => 'draft', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $question = $this->questionIn($school, $context['subject']);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $exam->schedule();
        $question->archive();
        $exam = $exam->fresh();

        // The exam's own question snapshot is unaffected by the source
        // question being archived after the fact — a student can still
        // start, answer and submit normally.
        $attempt = app(ExamAttemptService::class)->start($exam, $student);
        $this->assertSame(1, ExamAnswer::query()->where('exam_attempt_id', $attempt->id)->count());

        $this->app->forgetScopedInstances();
    }

    public function test_editing_the_source_question_after_attach_via_http_never_touches_the_snapshot(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $examinationQuestion = app(ExaminationQuestionService::class)->attach($exam, $question);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);
        $this->patch("/cbt/questions/{$question->id}", [
            'subject_id' => $context['subject']->id,
            'question_text' => 'A totally rewritten question, post-attach',
            'type' => 'multiple_choice',
            'difficulty' => 'hard',
            'marks' => 5,
            'options' => [
                ['option_text' => 'New A', 'is_correct' => 1],
                ['option_text' => 'New B', 'is_correct' => 0],
            ],
        ])->assertRedirect(route('cbt.questions.index'));

        $this->assertNotSame('A totally rewritten question, post-attach', $examinationQuestion->fresh()->question_text);
        $this->assertSame('1.00', (string) $examinationQuestion->fresh()->marks, 'the snapshot marks are untouched by the later edit');
    }
}
