<?php

namespace App\Http\Controllers\Cbt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\AttachQuestionRequest;
use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\Question;
use App\Services\Cbt\ExaminationQuestionService;
use App\Support\Cbt\CbtAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Attach/detach questions to a `draft` examination (M23, `docs/cbt.md`
 * §6). Gated the same as `ExaminationController` — a Teacher may only
 * touch an exam they're authorised to author for.
 */
class ExaminationQuestionController extends Controller
{
    public function __construct(
        private readonly CbtAuthorizer $authorizer,
        private readonly ExaminationQuestionService $questions,
    ) {}

    public function create(Request $request, int $examination): View
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);
        abort_unless($examination->status->structureEditable(), 403);

        $attachedQuestionIds = $examination->questions()->pluck('question_id')->filter()->all();

        return view('cbt.examinations.questions.create', [
            'examination' => $examination,
            'availableQuestions' => Question::query()
                ->active()
                ->where('subject_id', $examination->subject_id)
                ->whereNotIn('id', $attachedQuestionIds)
                ->with('options')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(AttachQuestionRequest $request, int $examination): RedirectResponse
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);

        $question = Question::query()->findOrFail($request->integer('question_id'));
        abort_unless($question->subject_id === $examination->subject_id, 422);

        try {
            $this->questions->attach($examination, $question);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Question added.'));
    }

    public function destroy(Request $request, int $examination, int $examinationQuestion): RedirectResponse
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);

        $examinationQuestion = ExaminationQuestion::query()
            ->where('examination_id', $examination->id)
            ->findOrFail($examinationQuestion);

        try {
            $this->questions->detach($examination, $examinationQuestion);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Question removed.'));
    }
}
