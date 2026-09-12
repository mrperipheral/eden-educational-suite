<?php

namespace Tests\Feature\Cbt;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\School;
use App\Services\Cbt\ExamAttemptException;
use App\Services\Cbt\ExamAttemptService;
use App\Services\Cbt\ExaminationQuestionService;
use Illuminate\Support\Carbon;

class ExamAttemptServiceTest extends CbtTestCase
{
    private function service(): ExamAttemptService
    {
        return app(ExamAttemptService::class);
    }

    /** A scheduled exam with two 1-mark MCQ questions, correct options at index 1. */
    private function scheduledExamWithQuestions(School $school, array $context, array $overrides = [])
    {
        $exam = $this->examinationIn($school, $context, array_merge(['status' => 'draft'], $overrides));
        $q1 = $this->questionIn($school, $context['subject']);
        $q2 = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $questionService = app(ExaminationQuestionService::class);
        $questionService->attach($exam, $q1);
        $questionService->attach($exam, $q2);

        $exam->refresh();
        $exam->schedule();
        $exam = $exam->fresh();

        $this->app->forgetScopedInstances();

        return $exam;
    }

    public function test_starting_an_attempt_creates_it_and_snapshots_blank_answers(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);

        $this->assertSame(ExamAttemptStatus::InProgress, $attempt->status);
        $this->assertSame(2, ExamAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
        $this->assertSame(2, ExamAnswer::query()->where('exam_attempt_id', $attempt->id)->whereNull('selected_option_id')->count());
        $this->assertTrue($attempt->expires_at->equalTo($attempt->started_at->copy()->addMinutes($exam->duration_minutes)));
    }

    public function test_a_second_start_for_the_same_student_resumes_not_duplicates(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $first = $this->service()->start($exam, $student);
        $second = $this->service()->start($exam, $student);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ExamAttempt::query()->where('examination_id', $exam->id)->where('student_id', $student->id)->count());
    }

    public function test_starting_a_completed_attempt_again_throws(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        $this->service()->submit($exam, $attempt);

        $this->expectException(ExamAttemptException::class);
        $this->service()->start($exam, $student);
    }

    public function test_cannot_start_an_attempt_outside_the_exam_window(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $this->expectException(ExamAttemptException::class);
        $this->service()->start($exam, $student);
    }

    public function test_answering_stores_the_selection_and_marks_answered_at(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        $question = $exam->questions()->with('options')->first();
        $option = $question->options->first();

        $this->service()->answer($exam, $attempt, $question, $option->id);

        $answer = ExamAnswer::query()->where('exam_attempt_id', $attempt->id)->where('examination_question_id', $question->id)->first();
        $this->assertSame($option->id, $answer->selected_option_id);
        $this->assertNotNull($answer->answered_at);
    }

    public function test_a_correct_and_a_wrong_answer_are_marked_and_scored_correctly(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2), 'pass_mark_percentage' => 50,
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        $questions = $exam->questions()->with('options')->get();

        // Question 1: answer correctly.
        $correct = $questions[0]->options->firstWhere('is_correct', true);
        $this->service()->answer($exam, $attempt, $questions[0], $correct->id);

        // Question 2: answer incorrectly.
        $wrong = $questions[1]->options->firstWhere('is_correct', false);
        $this->service()->answer($exam, $attempt, $questions[1], $wrong->id);

        $result = $this->service()->submit($exam, $attempt);

        $this->assertSame(ExamAttemptStatus::Completed, $result->status);
        $this->assertSame('1.00', (string) $result->score);
        $this->assertSame('2.00', (string) $result->max_score);
        $this->assertSame('50.00', (string) $result->percentage);
        $this->assertTrue($result->passed);
        $this->assertFalse($result->auto_submitted);
    }

    public function test_an_unanswered_question_scores_zero(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2), 'pass_mark_percentage' => 50,
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        // Leave both questions unanswered.
        $result = $this->service()->submit($exam, $attempt);

        $this->assertSame('0.00', (string) $result->score);
        $this->assertSame('0.00', (string) $result->percentage);
        $this->assertFalse($result->passed);
    }

    public function test_submitting_an_already_completed_attempt_is_idempotent(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        $first = $this->service()->submit($exam, $attempt);
        $secondSubmittedAt = $first->submitted_at->copy();

        $second = $this->service()->submit($exam, $first);

        $this->assertSame(ExamAttemptStatus::Completed, $second->status);
        $this->assertTrue($second->submitted_at->equalTo($secondSubmittedAt), 'resubmitting does not re-mark or re-stamp the attempt');
    }

    public function test_answering_after_submission_is_rejected(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);
        $this->service()->submit($exam, $attempt);

        $question = $exam->questions()->with('options')->first();

        $this->expectException(ExamAttemptException::class);
        $this->service()->answer($exam, $attempt->fresh(), $question, $question->options->first()->id);
    }

    public function test_server_side_expiry_prevents_further_answers_and_auto_submits(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subHours(3), 'ends_at' => now()->addHours(3), 'duration_minutes' => 30,
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        // Start "in the past" by directly creating an attempt whose
        // expires_at has already elapsed — simulating a student who has
        // been sitting the exam far longer than the allowed duration,
        // regardless of what their own browser/clock claims.
        $attempt = new ExamAttempt([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinutes(30),
        ]);
        $attempt->save();
        foreach ($exam->questions as $q) {
            ExamAnswer::query()->create(['exam_attempt_id' => $attempt->id, 'examination_question_id' => $q->id]);
        }

        $question = $exam->questions()->with('options')->first();

        $this->expectException(ExamAttemptException::class);
        $this->service()->answer($exam, $attempt, $question, $question->options->first()->id);
    }

    public function test_an_expired_attempt_is_automatically_finalised_on_next_touch(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subHours(3), 'ends_at' => now()->addHours(3), 'duration_minutes' => 30,
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = new ExamAttempt([
            'examination_id' => $exam->id,
            'student_id' => $student->id,
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinutes(30),
        ]);
        $attempt->save();
        foreach ($exam->questions as $q) {
            ExamAnswer::query()->create(['exam_attempt_id' => $attempt->id, 'examination_question_id' => $q->id]);
        }

        $finalized = $this->service()->finalizeIfExpired($exam, $attempt);

        $this->assertSame(ExamAttemptStatus::Completed, $finalized->status);
        $this->assertTrue($finalized->auto_submitted);
        $this->assertNotNull($finalized->submitted_at);
    }

    public function test_expires_at_never_exceeds_the_examinations_own_end_time(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        // Exam window closes in 10 minutes, but the duration is 30 —
        // the attempt must not get more time than the window allows.
        $exam = $this->scheduledExamWithQuestions($school, $context, [
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addMinutes(10), 'duration_minutes' => 30,
        ]);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $attempt = $this->service()->start($exam, $student);

        $this->assertTrue($attempt->expires_at->lessThanOrEqualTo($exam->ends_at));
        $this->assertTrue($attempt->expires_at->diffInMinutes(Carbon::now(), true) <= 11);
    }
}
