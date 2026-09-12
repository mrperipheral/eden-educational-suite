<?php

namespace App\Http\Controllers\Promotion;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\GraduateBatchRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\Student;
use App\Services\Promotion\GraduationService;
use App\Services\Promotion\PromotionEligibilityService;
use App\Services\Promotion\PromotionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Graduation — an explicit, authorised `StudentStatus` transition, never a
 * deletion. Gated `promotion.view` (history) / `graduation.manage`
 * (execute/reactivate), behind `module:promotion`. Not tied to any single
 * level: the candidate roster comes from whichever source class the
 * operator picks, exactly like promotion's own roster step, since not
 * every school graduates from the same level (M21 spec §5). See
 * `docs/promotion.md`.
 */
class GraduationController extends Controller
{
    public function __construct(
        private readonly PromotionEligibilityService $eligibility,
        private readonly GraduationService $graduation,
    ) {}

    public function index(): View
    {
        $this->authorize('promotion.view');

        return view('promotion.graduation.index', [
            'students' => Student::query()
                ->where('status', StudentStatus::Graduated->value)
                ->with(['graduatedSession:id,name', 'graduatedBy:id,name'])
                ->orderByDesc('graduated_at')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('graduation.manage');

        $students = collect();
        $sourceSession = $sourceLevel = $sourceArm = null;

        if ($request->filled('source_session') && $request->filled('source_level')) {
            $sourceSession = AcademicSession::query()->findOrFail($request->query('source_session'));
            $sourceLevel = AcademicLevel::query()->findOrFail($request->query('source_level'));
            $sourceArm = $request->query('source_arm') ? LevelArm::query()->findOrFail($request->query('source_arm')) : null;
            $students = $this->eligibility->eligibleStudents($sourceSession, $sourceLevel, $sourceArm);
        }

        return view('promotion.graduation.create', [
            'students' => $students,
            'sourceSession' => $sourceSession,
            'sourceLevel' => $sourceLevel,
            'sourceArm' => $sourceArm,
            'sessions' => AcademicSession::query()->orderByDesc('starts_on')->get(),
            'levels' => AcademicLevel::query()->with(['arms' => fn ($q) => $q->ordered()])->ordered()->get(),
        ]);
    }

    public function store(GraduateBatchRequest $request): RedirectResponse
    {
        $session = AcademicSession::query()->findOrFail($request->input('academic_session_id'));
        $students = Student::query()->whereKey($request->studentIds())->get();

        $result = $this->graduation->graduateBatch($students, $session, $request->notes(), $request->user());

        $status = $result['failed'] === 0
            ? __(':count student(s) graduated.', ['count' => $result['graduated']])
            : __(':graduated graduated, :failed could not be graduated.', ['graduated' => $result['graduated'], 'failed' => $result['failed']]);

        return to_route('promotion.graduation.index')->with($result['failed'] === 0 ? 'status' : 'error', $status);
    }

    public function reactivate(int $student): RedirectResponse
    {
        $this->authorize('graduation.manage');

        $student = Student::query()->findOrFail($student);

        try {
            $this->graduation->reactivate($student);
        } catch (PromotionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('students.show', $student)->with('status', __('Student reactivated.'));
    }
}
