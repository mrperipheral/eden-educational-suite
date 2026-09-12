<?php

namespace App\Http\Controllers\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromoteBatchRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\PromotionBatch;
use App\Services\Promotion\PromotionEligibilityService;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Bulk promotion — a two-step flow (pick the source class, then the
 * eligible roster + target class + confirm) rather than a stateful wizard,
 * so every step is a plain bookmarkable GET. Gated `promotion.view` (read)
 * / `promotion.manage` (write), behind `module:promotion`. `{batch}` is
 * resolved by tenant-scoped `findOrFail`, so another school's id 404s. See
 * `docs/promotion.md`.
 */
class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionEligibilityService $eligibility,
        private readonly PromotionService $promotions,
    ) {}

    public function index(): View
    {
        $this->authorize('promotion.view');

        return view('promotion.index', [
            'batches' => PromotionBatch::query()
                ->with(['sourceSession:id,name', 'sourceLevel:id,name', 'sourceArm:id,name', 'targetSession:id,name', 'targetLevel:id,name', 'targetArm:id,name', 'createdBy:id,name'])
                ->withCount(['records as promoted_count' => fn ($q) => $q->where('status', 'promoted')])
                ->ordered()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->authorize('promotion.manage');

        return view('promotion.create', $this->classOptions());
    }

    public function roster(Request $request): View
    {
        $this->authorize('promotion.manage');

        $sourceSession = AcademicSession::query()->findOrFail($request->query('source_session'));
        $sourceLevel = AcademicLevel::query()->findOrFail($request->query('source_level'));
        $sourceArm = $request->query('source_arm') ? LevelArm::query()->findOrFail($request->query('source_arm')) : null;

        $students = $this->eligibility->eligibleStudents($sourceSession, $sourceLevel, $sourceArm);

        return view('promotion.roster', [
            'sourceSession' => $sourceSession,
            'sourceLevel' => $sourceLevel,
            'sourceArm' => $sourceArm,
            'students' => $students,
            ...$this->classOptions(),
        ]);
    }

    public function store(PromoteBatchRequest $request): RedirectResponse
    {
        $sourceSession = AcademicSession::query()->findOrFail($request->input('source_academic_session_id'));
        $sourcePeriod = $request->filled('source_academic_period_id')
            ? AcademicPeriod::query()->find($request->input('source_academic_period_id'))
            : null;
        $sourceLevel = AcademicLevel::query()->findOrFail($request->input('source_academic_level_id'));
        $sourceArm = $request->filled('source_level_arm_id')
            ? LevelArm::query()->find($request->input('source_level_arm_id'))
            : null;

        $targetSession = AcademicSession::query()->findOrFail($request->input('target_academic_session_id'));
        $targetLevel = AcademicLevel::query()->findOrFail($request->input('target_academic_level_id'));
        $targetArm = $request->filled('target_level_arm_id')
            ? LevelArm::query()->find($request->input('target_level_arm_id'))
            : null;

        $batch = $this->promotions->promoteBatch(
            $sourceSession, $sourcePeriod, $sourceLevel, $sourceArm,
            $targetSession, $targetLevel, $targetArm,
            $request->studentIds(), $request->notes(), $request->user(),
        );

        return to_route('promotion.show', $batch)->with('status', __('Promotion complete.'));
    }

    public function show(int $batch): View
    {
        $this->authorize('promotion.view');

        $batch = PromotionBatch::query()
            ->with([
                'sourceSession:id,name', 'sourcePeriod:id,name', 'sourceLevel:id,name', 'sourceArm:id,name',
                'targetSession:id,name', 'targetLevel:id,name', 'targetArm:id,name', 'createdBy:id,name',
                'records' => fn ($q) => $q->with('student:id,first_name,last_name,preferred_name')->orderBy('id'),
            ])
            ->findOrFail($batch);

        return view('promotion.show', ['batch' => $batch]);
    }

    /**
     * @return array{sessions: Collection<int, AcademicSession>, levels: Collection<int, AcademicLevel>}
     */
    private function classOptions(): array
    {
        return [
            'sessions' => AcademicSession::query()
                ->with(['periods' => fn ($q) => $q->ordered()])
                ->orderByDesc('starts_on')
                ->get(),
            'levels' => AcademicLevel::query()
                ->with(['arms' => fn ($q) => $q->ordered()])
                ->ordered()
                ->get(),
        ];
    }
}
