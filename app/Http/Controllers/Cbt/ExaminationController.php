<?php

namespace App\Http\Controllers\Cbt;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cbt\ExaminationRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Examination;
use App\Support\Cbt\CbtAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * Create, edit, list, preview, schedule and close examinations (M23,
 * `docs/cbt.md`). Gated `cbt.view` (read) / `cbt.author` or `.manage`
 * (write), behind `module:cbt`. A Teacher holding `cbt.author` without
 * `.manage` is scoped to classes/subjects they actually teach by
 * `CbtAuthorizer` — the coarse permission alone cannot express that.
 */
class ExaminationController extends Controller
{
    public function __construct(private readonly CbtAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorize('cbt.view');

        $query = Examination::query()
            ->with(['session:id,name', 'level:id,name', 'arm:id,name', 'subject:id,name', 'createdBy:id,name'])
            ->withCount('questions')
            ->ordered();

        if ($request->filled('level')) {
            $query->where('academic_level_id', $request->integer('level'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('cbt.examinations.index', [
            'examinations' => $query->paginate(15)->withQueryString(),
            'levels' => AcademicLevel::query()->ordered()->get(),
            'filters' => $request->only(['level', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('cbt.author');

        return view('cbt.examinations.create', [
            ...$this->classOptions(),
            'unrestricted' => $request->user()->hasPermission(Permission::CbtManage),
            'assignments' => $this->authorizer->assignmentsFor($request->user()),
        ]);
    }

    public function store(ExaminationRequest $request): RedirectResponse
    {
        $context = $request->context();

        $allowed = $this->authorizer->canAuthorFor(
            $request->user(),
            $context['academic_level_id'],
            $context['level_arm_id'],
            $context['subject_id'],
        );
        abort_unless($allowed, 403);

        $examination = new Examination($context);
        $examination->created_by = $request->user()->id;
        $examination->save();

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Examination created. Add questions before scheduling it.'));
    }

    public function show(int $examination): View
    {
        $this->authorize('cbt.view');

        $examination = Examination::query()
            ->with(['session:id,name', 'period:id,name', 'level:id,name', 'arm:id,name', 'subject:id,name', 'createdBy:id,name'])
            ->withCount('attempts')
            ->findOrFail($examination);

        return view('cbt.examinations.show', [
            'examination' => $examination,
            'questions' => $examination->questions()->withCount('options')->get(),
            'canManage' => $this->authorizer->canManage(request()->user(), $examination),
        ]);
    }

    public function edit(Request $request, int $examination): View
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);
        abort_unless($examination->status->metadataEditable(), 403);

        return view('cbt.examinations.edit', [
            'examination' => $examination,
            ...$this->classOptions(),
        ]);
    }

    public function update(ExaminationRequest $request, int $examination): RedirectResponse
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($examination->status->metadataEditable(), 403);

        $context = $request->context();
        $allowed = $this->authorizer->canAuthorFor(
            $request->user(),
            $context['academic_level_id'],
            $context['level_arm_id'],
            $context['subject_id'],
        );
        abort_unless($allowed, 403);

        $examination->fill($context);
        $examination->save();

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Examination updated.'));
    }

    public function preview(Request $request, int $examination): View
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination) || $request->user()->hasPermission(Permission::CbtView), 403);

        return view('cbt.examinations.preview', [
            'examination' => $examination,
            'questions' => $examination->questions()->with('options')->get(),
        ]);
    }

    public function schedule(Request $request, int $examination): RedirectResponse
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);

        try {
            $examination->schedule();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Examination scheduled — it is now visible to its class.'));
    }

    public function close(Request $request, int $examination): RedirectResponse
    {
        $examination = Examination::query()->findOrFail($examination);
        abort_unless($this->authorizer->canManage($request->user(), $examination), 403);

        try {
            $examination->close();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('cbt.examinations.show', $examination->id)->with('status', __('Examination closed.'));
    }

    /**
     * @return array{sessions: Collection, levels: Collection}
     */
    private function classOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered(), 'subjects' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
        ];
    }
}
