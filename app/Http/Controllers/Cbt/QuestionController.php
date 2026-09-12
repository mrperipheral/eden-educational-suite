<?php

namespace App\Http\Controllers\Cbt;

use App\Enums\ExaminationQuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\QuestionRequest;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reusable, tenant-scoped question bank (M23, `docs/cbt.md` §5) —
 * deliberately minimal groundwork for a future M24 Question Bank, not the
 * bank itself. Gated `cbt.view` (read) / `cbt.author` or `.manage`
 * (write), behind `module:cbt`. Not class-scoped — any author may create a
 * question for any subject; only *attaching* one to a specific exam is
 * scoped to the teacher's own assigned classes
 * (`App\Http\Controllers\Cbt\ExaminationQuestionController`).
 */
class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('cbt.view');

        $query = Question::query()
            ->with(['subject:id,name', 'options'])
            ->ordered();

        if ($request->filled('subject')) {
            $query->where('subject_id', $request->integer('subject'));
        }

        return view('cbt.questions.index', [
            'questions' => $query->paginate(15)->withQueryString(),
            'subjects' => Subject::query()->ordered()->get(),
            'filters' => $request->only(['subject']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('cbt.author');

        return view('cbt.questions.create', [
            'subjects' => Subject::query()->ordered()->get(),
            'types' => ExaminationQuestionType::all(),
        ]);
    }

    public function store(QuestionRequest $request): RedirectResponse
    {
        $question = new Question($request->context());
        $question->type = $request->input('type');
        $question->created_by = $request->user()->id;
        $question->save();

        foreach ($request->options() as $position => $option) {
            $question->options()->create([
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
                'position' => $position + 1,
            ]);
        }

        return to_route('cbt.questions.index')->with('status', __('Question created.'));
    }

    public function edit(int $question): View
    {
        $this->authorize('cbt.author');

        $question = Question::query()->with('options')->findOrFail($question);

        return view('cbt.questions.edit', [
            'question' => $question,
            'subjects' => Subject::query()->ordered()->get(),
            'types' => ExaminationQuestionType::all(),
        ]);
    }

    public function update(QuestionRequest $request, int $question): RedirectResponse
    {
        $question = Question::query()->findOrFail($question);

        $question->fill($request->context());
        $question->type = $request->input('type');
        $question->save();

        $question->options()->delete();
        foreach ($request->options() as $position => $option) {
            $question->options()->create([
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
                'position' => $position + 1,
            ]);
        }

        return to_route('cbt.questions.index')->with('status', __('Question updated.'));
    }

    public function toggleActive(int $question): RedirectResponse
    {
        $this->authorize('cbt.author');

        $question = Question::query()->findOrFail($question);
        $question->is_active = ! $question->is_active;
        $question->save();

        return back()->with('status', $question->is_active ? __('Question activated.') : __('Question deactivated.'));
    }
}
