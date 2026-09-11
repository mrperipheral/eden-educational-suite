<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\FeeStructureRequest;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Fee structures — "this category costs this amount for this
 * session/period/level(/arm)". Gated `fees.view` (read) and `fees.manage`
 * (write), behind `module:fees`. `{structure}` is resolved by tenant-scoped
 * `findOrFail`, so another school's id 404s. Freely editable — see
 * `App\Models\FeeStructure` for why editing one never touches a charge
 * already raised from it.
 */
class FeeStructureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('fees.manage');

        $sessionId = (int) $request->query('session') ?: null;
        $levelId = (int) $request->query('level') ?: null;

        $structures = FeeStructure::query()
            ->with(['category:id,name', 'session:id,name', 'period:id,name', 'level:id,name', 'arm:id,name'])
            ->when($sessionId, fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($levelId, fn ($q, $id) => $q->where('academic_level_id', $id))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('fees.structures.index', [
            'structures' => $structures,
            'filters' => ['session' => $sessionId, 'level' => $levelId],
            ...$this->options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('fees.manage');

        return view('fees.structures.create', [
            'structure' => new FeeStructure,
            ...$this->options(),
        ]);
    }

    public function store(FeeStructureRequest $request): RedirectResponse
    {
        $structure = new FeeStructure($request->payload());
        $structure->created_by = $request->user()->getKey();
        $structure->save();

        return to_route('fees.structures.index')->with('status', __('Fee structure added.'));
    }

    public function edit(int $structure): View
    {
        $this->authorize('fees.manage');

        return view('fees.structures.edit', [
            'structure' => FeeStructure::query()->findOrFail($structure),
            ...$this->options(),
        ]);
    }

    public function update(FeeStructureRequest $request, int $structure): RedirectResponse
    {
        $structure = FeeStructure::query()->findOrFail($structure);
        $structure->update($request->payload());

        return to_route('fees.structures.index')->with('status', __('Fee structure updated.'));
    }

    /**
     * @return array{categories: Collection, sessions: Collection, levels: Collection}
     */
    private function options(): array
    {
        return [
            'categories' => FeeCategory::query()->active()->ordered()->get(),
            'sessions' => AcademicSession::query()->with(['periods' => fn ($q) => $q->ordered()])->orderByDesc('starts_on')->get(),
            'levels' => AcademicLevel::query()->with(['arms' => fn ($q) => $q->ordered()])->ordered()->get(),
        ];
    }
}
