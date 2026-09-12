<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ExaminationStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\ExaminationQuestionOption;
use App\Models\Student;
use App\Services\Cbt\ExamAttemptException;
use App\Services\Cbt\ExamAttemptService;
use App\Support\Cbt\CbtAuthorizer;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Start, take, answer and submit a student's own exam attempt (M23,
 * `docs/cbt.md` §7–9). Every action re-resolves the signed-in student and
 * re-checks class eligibility from scratch — a `{examination}` id is never
 * trusted alone, and neither is any id embedded in the request body
 * (`student_id`/`school_id`/marks/correctness are never accepted from the
 * client at all). Gated `cbt.take`, behind `module:cbt`.
 */
class StudentExamAttemptController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly CbtAuthorizer $cbtAuthorizer,
        private readonly ExamAttemptService $attempts,
    ) {}

    public function start(Request $request, int $examination): RedirectResponse
    {
        $this->authorize('cbt.take');
        [$student, $examination] = $this->resolveEligible($request, $examination);

        try {
            $this->attempts->start($examination, $student);
        } catch (ExamAttemptException $e) {
            return to_route('student.cbt.show', $examination->id)->with('error', $e->getMessage());
        }

        return to_route('student.cbt.take', $examination->id);
    }

    public function take(Request $request, int $examination): View|RedirectResponse
    {
        $this->authorize('cbt.take');
        [$student, $examination] = $this->resolveEligible($request, $examination);

        $attempt = $this->attemptFor($examination, $student);
        abort_if($attempt === null, 404);

        $attempt = $this->attempts->finalizeIfExpired($examination, $attempt);
        if ($attempt->isCompleted()) {
            return to_route('student.cbt.result', $examination->id);
        }

        $questions = ExaminationQuestion::query()
            ->where('examination_id', $examination->id)
            ->with(['options' => fn ($q) => $q->select('id', 'examination_question_id', 'option_text', 'position')])
            ->orderBy('position')
            ->get();

        $answers = ExamAnswer::query()
            ->where('exam_attempt_id', $attempt->id)
            ->get()
            ->keyBy('examination_question_id');

        return view('student.cbt.take', [
            'examination' => $examination,
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => $answers,
        ]);
    }

    public function answer(Request $request, int $examination): JsonResponse
    {
        $this->authorize('cbt.take');
        [$student, $examination] = $this->resolveEligible($request, $examination);

        $attempt = $this->attemptFor($examination, $student);
        abort_if($attempt === null, 404);

        $validated = $request->validate([
            'examination_question_id' => ['required', 'integer'],
            'selected_option_id' => ['nullable', 'integer'],
        ]);

        $question = ExaminationQuestion::query()
            ->where('examination_id', $examination->id)
            ->find($validated['examination_question_id']);

        if ($question === null) {
            return response()->json(['message' => __('Invalid question.')], 422);
        }

        $selectedOptionId = $validated['selected_option_id'] ?? null;
        if ($selectedOptionId !== null) {
            $optionBelongs = ExaminationQuestionOption::query()
                ->where('examination_question_id', $question->id)
                ->whereKey($selectedOptionId)
                ->exists();

            if (! $optionBelongs) {
                return response()->json(['message' => __('Invalid option.')], 422);
            }
        }

        try {
            $this->attempts->answer($examination, $attempt, $question, $selectedOptionId);
        } catch (ExamAttemptException $e) {
            return response()->json(['message' => $e->getMessage(), 'expired' => true], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, int $examination): RedirectResponse
    {
        $this->authorize('cbt.take');
        [$student, $examination] = $this->resolveEligible($request, $examination);

        $attempt = $this->attemptFor($examination, $student);
        abort_if($attempt === null, 404);

        $this->attempts->submit($examination, $attempt);

        return to_route('student.cbt.result', $examination->id)->with('status', __('Examination submitted.'));
    }

    public function result(Request $request, int $examination): View
    {
        $this->authorize('cbt.take');
        [$student, $examination] = $this->resolveEligible($request, $examination);

        $attempt = $this->attemptFor($examination, $student);

        return view('student.cbt.result', [
            'examination' => $examination,
            'attempt' => $attempt,
            'resultVisible' => $attempt !== null && $attempt->isResultVisible(),
        ]);
    }

    /**
     * @return array{0: Student, 1: Examination}
     */
    private function resolveEligible(Request $request, int $examinationId): array
    {
        $student = $this->authorizer->studentFor($request->user());
        abort_if($student === null, 404);
        $student->loadMissing('currentEnrollment');

        $examination = Examination::query()
            ->where('status', '!=', ExaminationStatus::Draft->value)
            ->findOrFail($examinationId);

        abort_unless($this->cbtAuthorizer->studentCanAccess($student, $examination), 404);

        return [$student, $examination];
    }

    private function attemptFor(Examination $examination, Student $student): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->first();
    }
}
