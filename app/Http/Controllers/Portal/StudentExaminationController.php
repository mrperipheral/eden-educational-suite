<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ExaminationStatus;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Support\Cbt\CbtAuthorizer;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\StudentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in student's own class's examinations (M23, `docs/cbt.md`
 * §10). Scoped to the student's *current* enrollment (session + level +
 * arm) — exact match, never a "whole level" broadcast like Learning
 * Materials, since an exam is always one specific class's exam. `draft`
 * exams are never shown. Degrades cleanly when the module is off or the
 * student has no current enrollment (an empty state, like
 * `StudentAssignmentController`/`StudentLearningMaterialController`),
 * never a 404 on the portal's own nav.
 */
class StudentExaminationController extends Controller
{
    public function __construct(
        private readonly StudentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
        private readonly CbtAuthorizer $cbtAuthorizer,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('cbt.take');

        $student = $this->authorizer->studentFor($request->user());
        $student?->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['level', 'arm'])]);
        $moduleOn = $this->modules->enabled(Module::Cbt);
        $enrollment = $student?->currentEnrollment;

        $examinations = null;

        if ($moduleOn && $enrollment !== null) {
            $examinations = Examination::query()
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->where('academic_level_id', $enrollment->academic_level_id)
                ->where('level_arm_id', $enrollment->level_arm_id)
                ->where('status', '!=', ExaminationStatus::Draft->value)
                ->with(['subject:id,name'])
                ->withCount('questions')
                ->ordered()
                ->paginate(15)
                ->withQueryString();

            $attemptStatuses = $student !== null
                ? ExamAttempt::query()
                    ->where('student_id', $student->id)
                    ->whereIn('examination_id', $examinations->pluck('id'))
                    ->get()
                    ->keyBy('examination_id')
                : collect();
        }

        return view('student.cbt.index', [
            'student' => $student,
            'moduleOn' => $moduleOn,
            'examinations' => $examinations,
            'attemptStatuses' => $attemptStatuses ?? collect(),
        ]);
    }

    public function show(Request $request, int $examination): View
    {
        $this->authorize('cbt.take');

        $student = $this->authorizer->studentFor($request->user());
        abort_if($student === null, 404);
        $student->loadMissing('currentEnrollment');

        $examination = Examination::query()
            ->where('status', '!=', ExaminationStatus::Draft->value)
            ->with(['subject:id,name'])
            ->withCount('questions')
            ->findOrFail($examination);

        abort_unless($this->cbtAuthorizer->studentCanAccess($student, $examination), 404);

        $attempt = ExamAttempt::query()
            ->where('examination_id', $examination->id)
            ->where('student_id', $student->id)
            ->first();

        return view('student.cbt.show', [
            'examination' => $examination,
            'attempt' => $attempt,
        ]);
    }
}
