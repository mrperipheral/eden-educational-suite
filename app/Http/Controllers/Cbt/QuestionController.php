<?php

namespace App\Http\Controllers\Cbt;

use App\Enums\ExaminationQuestionType;
use App\Enums\Permission;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\QuestionRequest;
use App\Models\AcademicLevel;
use App\Models\Question;
use App\Models\Subject;
use App\Services\Audit\AuditRecorder;
use App\Support\Cbt\CbtAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The reusable, tenant-scoped Question Bank (M24, `docs/question-bank.md`;
 * introduced minimally in M23, `docs/cbt.md` §5). Gated `cbt.view` (read) /
 * `cbt.author` or `.manage` (write), behind `module:cbt`. A Teacher
 * holding `cbt.author` without `.manage` may only create/edit/archive a
 * question for a subject (+ level/arm, if set) they hold an active M11
 * `TeacherAssignment` for — re-checked server-side via
 * `App\Support\Cbt\CbtAuthorizer::canManageQuestionFor()`, never trusted
 * from the request alone. *Viewing* the bank (including a scoped Teacher's
 * own list) is not further restricted — it's a shared, reusable resource.
 */
class QuestionController extends Controller
{
    public function __construct(private readonly CbtAuthorizer $authorizer, private readonly AuditRecorder $audit) {}

    private function questionLabel(Question $question): string
    {
        return Str::limit($question->question_text, 80);
    }

    public function index(Request $request): View
    {
        $this->authorize('cbt.view');

        $query = Question::query()
            ->with(['subject:id,name', 'level:id,name', 'arm:id,name', 'options'])
            ->ordered();

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim()->value().'%';
            $query->where(fn ($q) => $q->where('question_text', 'like', $term)->orWhere('topic', 'like', $term));
        }
        if ($request->filled('subject')) {
            $query->where('subject_id', $request->integer('subject'));
        }
        if ($request->filled('level')) {
            $query->where('academic_level_id', $request->integer('level'));
        }
        if ($request->filled('arm')) {
            $query->where('level_arm_id', $request->integer('arm'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('cbt.questions.index', [
            'questions' => $query->paginate(15)->withQueryString(),
            'subjects' => Subject::query()->ordered()->get(),
            'levels' => AcademicLevel::query()->with(['arms' => fn ($q) => $q->ordered()])->ordered()->get(),
            'types' => ExaminationQuestionType::all(),
            'difficulties' => QuestionDifficulty::all(),
            'statuses' => QuestionStatus::all(),
            'filters' => $request->only(['q', 'subject', 'level', 'arm', 'type', 'difficulty', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('cbt.author');

        return view('cbt.questions.create', [
            ...$this->classOptions(),
            'types' => ExaminationQuestionType::all(),
            'difficulties' => QuestionDifficulty::all(),
            'unrestricted' => $request->user()->hasPermission(Permission::CbtManage),
            'assignments' => $this->authorizer->assignmentsFor($request->user()),
        ]);
    }

    public function store(QuestionRequest $request): RedirectResponse
    {
        $context = $request->context();

        $allowed = $this->authorizer->canManageQuestionFor(
            $request->user(),
            $context['subject_id'],
            $context['academic_level_id'],
            $context['level_arm_id'],
        );
        abort_unless($allowed, 403);

        $question = new Question($context);
        $question->type = $request->input('type');
        $question->difficulty = $request->input('difficulty');
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

    public function edit(Request $request, int $question): View
    {
        $question = Question::query()->with('options')->findOrFail($question);
        abort_unless($this->authorizer->canManageQuestion($request->user(), $question), 403);

        return view('cbt.questions.edit', [
            'question' => $question,
            ...$this->classOptions(),
            'types' => ExaminationQuestionType::all(),
            'difficulties' => QuestionDifficulty::all(),
        ]);
    }

    public function update(QuestionRequest $request, int $question): RedirectResponse
    {
        $question = Question::query()->findOrFail($question);
        abort_unless($this->authorizer->canManageQuestion($request->user(), $question), 403);

        $context = $request->context();
        $allowed = $this->authorizer->canManageQuestionFor(
            $request->user(),
            $context['subject_id'],
            $context['academic_level_id'],
            $context['level_arm_id'],
        );
        abort_unless($allowed, 403);

        $question->fill($context);
        $question->type = $request->input('type');
        $question->difficulty = $request->input('difficulty');
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

    public function preview(int $question): View
    {
        $this->authorize('cbt.view');

        $question = Question::query()->with(['subject:id,name', 'level:id,name', 'arm:id,name', 'options'])->findOrFail($question);

        return view('cbt.questions.preview', ['question' => $question]);
    }

    public function activate(Request $request, int $question): RedirectResponse
    {
        $question = Question::query()->findOrFail($question);
        abort_unless($this->authorizer->canManageQuestion($request->user(), $question), 403);

        $previousStatus = $question->status;
        $question->activate();
        $this->recordStatusChange($request, $question, $previousStatus);

        return back()->with('status', __('Question activated.'));
    }

    public function deactivate(Request $request, int $question): RedirectResponse
    {
        $question = Question::query()->findOrFail($question);
        abort_unless($this->authorizer->canManageQuestion($request->user(), $question), 403);

        $previousStatus = $question->status;
        $question->deactivate();
        $this->recordStatusChange($request, $question, $previousStatus);

        return back()->with('status', __('Question deactivated.'));
    }

    public function archive(Request $request, int $question): RedirectResponse
    {
        $question = Question::query()->findOrFail($question);
        abort_unless($this->authorizer->canManageQuestion($request->user(), $question), 403);

        $previousStatus = $question->status;
        $question->archive();
        $this->recordStatusChange($request, $question, $previousStatus);

        return back()->with('status', __('Question archived.'));
    }

    private function recordStatusChange(Request $request, Question $question, QuestionStatus $previousStatus): void
    {
        $this->audit->record(
            event: 'question.status_changed',
            summary: __(':actor changed a question\'s status from :from to :to.', [
                'actor' => $request->user()->name, 'from' => $previousStatus->label(), 'to' => $question->status->label(),
            ]),
            auditable: $question,
            auditableLabel: $this->questionLabel($question),
            before: ['status' => $previousStatus->value],
            after: ['status' => $question->status->value],
        );
    }

    /**
     * @return array{subjects: Collection, levels: Collection}
     */
    private function classOptions(): array
    {
        return [
            'subjects' => Subject::query()->ordered()->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered(), 'subjects' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
        ];
    }
}
