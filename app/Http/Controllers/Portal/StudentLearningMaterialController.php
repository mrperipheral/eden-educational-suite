<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in student's own class's learning materials (M22,
 * `docs/learning-materials.md`). Scoped to the student's *current*
 * enrollment (session + level + arm) — a material for a whole level
 * (`level_arm_id` null) is visible to every arm of that level, a material
 * for one specific arm only to that arm. Never scoped by subject — a
 * material's `subject_id` is a label, not an extra access restriction,
 * since every student in a level already takes the same subject list.
 * Degrades cleanly when the module is off (`index`), like
 * `StudentAssignmentController`/`StudentTimetableController`.
 */
class StudentLearningMaterialController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.student');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['level', 'arm'])]);
        $moduleOn = $this->modules->enabled(Module::LearningMaterials);
        $enrollment = $student?->currentEnrollment;

        $materials = ($moduleOn && $enrollment !== null)
            ? LearningMaterial::query()
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->forClass($enrollment->academic_level_id, $enrollment->level_arm_id)
                ->with(['subject:id,name'])
                ->ordered()
                ->paginate(15)
                ->withQueryString()
            : null;

        return view('student.learning-materials.index', [
            'student' => $student,
            'materials' => $materials,
            'moduleOn' => $moduleOn,
        ]);
    }

    public function download(Request $request, int $material): StreamedResponse
    {
        $this->authorize('portal.student');

        abort_unless($this->modules->enabled(Module::LearningMaterials), 404);

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing('currentEnrollment');
        $enrollment = $student?->currentEnrollment;

        abort_if($enrollment === null, 404);

        $material = LearningMaterial::query()
            ->where('academic_session_id', $enrollment->academic_session_id)
            ->forClass($enrollment->academic_level_id, $enrollment->level_arm_id)
            ->findOrFail($material);

        abort_unless($material->fileExists(), 404);

        return Storage::disk(LearningMaterial::FILE_DISK)->download($material->file_path, $material->file_name);
    }
}
