<?php

namespace App\Services\Cbt;

use App\Enums\ExamAttemptStatus;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Starts, answers and submits a student's {@see ExamAttempt} — the single
 * seam every student-facing CBT action goes through, so server-side timing
 * and marking are enforced in exactly one place. See `docs/cbt.md` §7–9.
 *
 * The browser's own clock/timer is never trusted for anything beyond
 * display — every write here re-checks `Carbon::now()` against the
 * attempt's own `expires_at` (never a client-submitted value) before
 * accepting an answer or letting a submission stand.
 */
class ExamAttemptService
{
    /**
     * Starts a new attempt, or safely resumes an existing in-progress one
     * (a browser refresh/reconnect must never lose or duplicate the
     * server-side attempt). Bulk-inserts one unanswered {@see ExamAnswer}
     * per {@see ExaminationQuestion} up front — mirrors M13's own
     * `AttendanceRecord` roster-snapshot convention.
     *
     * @throws ExamAttemptException
     */
    public function start(Examination $examination, Student $student): ExamAttempt
    {
        $existing = ExamAttempt::query()
            ->where('examination_id', $examination->getKey())
            ->where('student_id', $student->getKey())
            ->first();

        if ($existing !== null) {
            $existing = $this->finalizeIfExpired($examination, $existing);

            if ($existing->isInProgress()) {
                return $existing;
            }

            throw new ExamAttemptException(__('You have already attempted this examination.'));
        }

        if (! $examination->isOpenForAttempts()) {
            throw new ExamAttemptException(__('This examination is not currently open.'));
        }

        try {
            return DB::transaction(function () use ($examination, $student) {
                $now = Carbon::now();
                $expiresAt = $now->copy()->addMinutes($examination->duration_minutes);
                if ($expiresAt->gt($examination->ends_at)) {
                    $expiresAt = $examination->ends_at->copy();
                }

                $attempt = new ExamAttempt([
                    'examination_id' => $examination->getKey(),
                    'student_id' => $student->getKey(),
                    'started_at' => $now,
                    'expires_at' => $expiresAt,
                ]);
                $attempt->save();

                $questions = $examination->questions;
                $rows = $questions->map(fn (ExaminationQuestion $q) => [
                    'school_id' => $examination->school_id,
                    'exam_attempt_id' => $attempt->getKey(),
                    'examination_question_id' => $q->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    ExamAnswer::query()->insert($rows);
                }

                return $attempt;
            });
        } catch (QueryException) {
            // Unique(examination_id, student_id) lost the race to a
            // concurrent request — resume the attempt it created instead
            // of erroring (two tabs, a double-click, a retried request).
            return ExamAttempt::query()
                ->where('examination_id', $examination->getKey())
                ->where('student_id', $student->getKey())
                ->firstOrFail();
        }
    }

    /**
     * Records (or clears) a student's selected option for one question.
     * Rejects the write outright if the attempt is already completed or
     * has just expired — auto-finalising it first if so.
     *
     * @throws ExamAttemptException
     */
    public function answer(Examination $examination, ExamAttempt $attempt, ExaminationQuestion $question, ?int $selectedOptionId): ExamAttempt
    {
        $attempt = $this->assertActionable($examination, $attempt);

        ExamAnswer::query()
            ->where('exam_attempt_id', $attempt->getKey())
            ->where('examination_question_id', $question->getKey())
            ->update([
                'selected_option_id' => $selectedOptionId,
                'answered_at' => Carbon::now(),
            ]);

        return $attempt;
    }

    /**
     * Finalises a student-initiated submission. Idempotent — resubmitting
     * an already-completed attempt is a safe no-op, never a duplicate
     * marking pass or an error.
     */
    public function submit(Examination $examination, ExamAttempt $attempt): ExamAttempt
    {
        return $this->finalize($examination, $attempt, auto: false);
    }

    /**
     * Auto-finalises an attempt whose deadline has passed, if it hasn't
     * been finalised already. Safe to call on every request that touches
     * an attempt — a no-op when the attempt is still within time.
     */
    public function finalizeIfExpired(Examination $examination, ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isInProgress() && $attempt->isExpired()) {
            return $this->finalize($examination, $attempt, auto: true);
        }

        return $attempt;
    }

    /**
     * @throws ExamAttemptException if the attempt cannot currently accept an answer
     */
    public function assertActionable(Examination $examination, ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isCompleted()) {
            throw new ExamAttemptException(__('This attempt has already been submitted.'));
        }

        $attempt = $this->finalizeIfExpired($examination, $attempt);

        if ($attempt->isCompleted()) {
            throw new ExamAttemptException(__('Time is up — this attempt was automatically submitted.'));
        }

        return $attempt;
    }

    /**
     * The actual marking pass: compares every stored answer's selected
     * option against the snapshotted `ExaminationQuestionOption.is_correct`
     * — never a client-submitted correctness/marks value. Row-locks the
     * attempt so a concurrent expiry-triggered and student-triggered
     * submission can never both mark it.
     */
    private function finalize(Examination $examination, ExamAttempt $attempt, bool $auto): ExamAttempt
    {
        return DB::transaction(function () use ($examination, $attempt, $auto) {
            $locked = ExamAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->isCompleted()) {
                return $locked ?? $attempt;
            }

            $answers = ExamAnswer::query()
                ->where('exam_attempt_id', $locked->getKey())
                ->with(['examinationQuestion', 'selectedOption'])
                ->get();

            $score = '0';
            $maxScore = '0';

            foreach ($answers as $answer) {
                $marks = (string) $answer->examinationQuestion->marks;
                $maxScore = bcadd($maxScore, $marks, 2);

                $isCorrect = $answer->selectedOption !== null && $answer->selectedOption->is_correct;
                $awarded = $isCorrect ? $marks : '0.00';

                $answer->is_correct = $isCorrect;
                $answer->marks_awarded = $awarded;
                $answer->save();

                if ($isCorrect) {
                    $score = bcadd($score, $marks, 2);
                }
            }

            $percentage = bccomp($maxScore, '0', 2) > 0
                ? round(((float) $score / (float) $maxScore) * 100, 2)
                : 0.0;

            $locked->status = ExamAttemptStatus::Completed;
            $locked->submitted_at = Carbon::now();
            $locked->score = $score;
            $locked->max_score = $maxScore;
            $locked->percentage = $percentage;
            $locked->passed = $percentage >= (float) $examination->pass_mark_percentage;
            $locked->auto_submitted = $auto;
            $locked->save();

            return $locked;
        });
    }
}
