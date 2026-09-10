<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\LevelRequest;
use App\Http\Requests\Academic\SyncLevelSubjectsRequest;
use App\Models\AcademicLevel;
use App\Models\Subject;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Academic levels / classes and their subject offerings. `AcademicLevel` is a
 * `BelongsToSchool` model resolved with tenant-scoped `findOrFail`.
 *
 * Gated by `academics.view` / `academics.manage`; behind `module:academics`.
 */
class LevelController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): View
    {
        $this->authorize('academics.view');

        return view('academic.levels.index', [
            'levels' => AcademicLevel::query()
                ->withCount(['arms', 'subjects'])
                ->ordered()
                ->paginate(30),
        ]);
    }

    public function store(LevelRequest $request): RedirectResponse
    {
        $level = AcademicLevel::create($request->validated());

        return to_route('academic.levels.index')
            ->with('status', __('Level ":name" created.', ['name' => $level->name]));
    }

    public function show(int $level): View
    {
        $this->authorize('academics.view');

        $level = AcademicLevel::query()
            ->with(['arms' => fn ($q) => $q->ordered()])
            ->findOrFail($level);

        return view('academic.levels.show', [
            'level' => $level,
            'offeredSubjectIds' => $level->subjects()->pluck('subjects.id')->all(),
            'subjects' => Subject::query()->ordered()->get(),
        ]);
    }

    public function edit(int $level): View
    {
        $this->authorize('academics.manage');

        return view('academic.levels.edit', [
            'level' => AcademicLevel::query()->findOrFail($level),
        ]);
    }

    public function update(LevelRequest $request, int $level): RedirectResponse
    {
        $model = AcademicLevel::query()->findOrFail($level);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;
        $model->update($data);

        return to_route('academic.levels.index')->with('status', __('Level updated.'));
    }

    public function syncSubjects(SyncLevelSubjectsRequest $request, int $level): RedirectResponse
    {
        $model = AcademicLevel::query()->findOrFail($level);

        // Pivot rows carry school_id from the tenant context; the ids are already
        // validated to belong to the active school.
        $schoolId = $this->tenant->idOrFail();
        $model->subjects()->sync(
            collect($request->subjectIds())
                ->mapWithKeys(fn (int $id) => [$id => ['school_id' => $schoolId]])
                ->all()
        );

        return to_route('academic.levels.show', $model)
            ->with('status', __('Subjects updated for :level.', ['level' => $model->name]));
    }
}
