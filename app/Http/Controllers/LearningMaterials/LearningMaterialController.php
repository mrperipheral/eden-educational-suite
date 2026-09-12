<?php

namespace App\Http\Controllers\LearningMaterials;

use App\Enums\LearningMaterialType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\LearningMaterials\LearningMaterialRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Services\LearningMaterials\LearningMaterialUploadService;
use App\Services\LearningMaterials\UnsupportedMaterialTypeException;
use App\Support\LearningMaterials\LearningMaterialAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, list, download and delete learning materials (M22,
 * `docs/learning-materials.md`). Gated `material.view` (read) /
 * `.upload` or `.manage` (write), behind `module:learning-materials`. A
 * Teacher holding `.upload` without `.manage` is scoped to classes/subjects
 * they actually teach by `LearningMaterialAuthorizer` — the coarse
 * permission alone cannot express that.
 */
class LearningMaterialController extends Controller
{
    public function __construct(
        private readonly LearningMaterialAuthorizer $authorizer,
        private readonly LearningMaterialUploadService $uploads,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('material.view');

        $query = LearningMaterial::query()
            ->with(['session:id,name', 'level:id,name', 'arm:id,name', 'subject:id,name', 'uploadedBy:id,name'])
            ->ordered();

        if ($request->filled('level')) {
            $query->where('academic_level_id', $request->integer('level'));
        }
        if ($request->filled('subject')) {
            $query->where('subject_id', $request->integer('subject'));
        }

        return view('learning-materials.index', [
            'materials' => $query->paginate(15)->withQueryString(),
            'levels' => AcademicLevel::query()->ordered()->get(),
            'subjects' => Subject::query()->ordered()->get(),
            'filters' => $request->only(['level', 'subject']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('material.upload');

        return view('learning-materials.create', [
            ...$this->classOptions(),
            'unrestricted' => $request->user()->hasPermission(Permission::LearningMaterialManage),
            'assignments' => $this->authorizer->assignmentsFor($request->user()),
            'types' => LearningMaterialType::enabled(),
        ]);
    }

    public function store(LearningMaterialRequest $request): RedirectResponse
    {
        $context = $request->context();

        $allowed = $this->authorizer->canUploadFor(
            $request->user(),
            $context['academic_level_id'],
            $context['level_arm_id'],
            $context['subject_id'],
        );
        abort_unless($allowed, 403);

        try {
            $this->uploads->upload($context, $request->file('file'), $request->user());
        } catch (UnsupportedMaterialTypeException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        return to_route('learning-materials.index')->with('status', __('Learning material uploaded.'));
    }

    public function download(int $material): StreamedResponse
    {
        $this->authorize('material.view');

        $material = LearningMaterial::query()->findOrFail($material);
        abort_unless($material->fileExists(), 404);

        return Storage::disk(LearningMaterial::FILE_DISK)->download($material->file_path, $material->file_name);
    }

    public function destroy(Request $request, int $material): RedirectResponse
    {
        $material = LearningMaterial::query()->findOrFail($material);
        abort_unless($this->authorizer->canDelete($request->user(), $material), 403);

        $material->deleteWithFile();

        return to_route('learning-materials.index')->with('status', __('Learning material deleted.'));
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
