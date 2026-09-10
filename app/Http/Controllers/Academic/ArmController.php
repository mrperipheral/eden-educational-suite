<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ArmRequest;
use App\Models\AcademicLevel;
use App\Models\LevelArm;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Arms / streams within a level. Both `AcademicLevel` and `LevelArm` are
 * `BelongsToSchool` and resolved with tenant-scoped `findOrFail`.
 *
 * Gated by `academics.manage`; behind `module:academics`.
 */
class ArmController extends Controller
{
    public function store(ArmRequest $request, int $level): RedirectResponse
    {
        $level = AcademicLevel::query()->findOrFail($level);
        $data = $request->validated();

        $arm = $level->arms()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'position' => $data['position'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return to_route('academic.levels.show', $level)
            ->with('status', __('Arm ":name" added.', ['name' => $arm->name]));
    }

    public function edit(int $arm): View
    {
        $this->authorize('academics.manage');

        return view('academic.arms.edit', [
            'arm' => LevelArm::query()->with('level')->findOrFail($arm),
        ]);
    }

    public function update(ArmRequest $request, int $arm): RedirectResponse
    {
        $arm = LevelArm::query()->findOrFail($arm);

        $data = $request->validated();
        $arm->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'position' => $data['position'],
            'is_active' => $data['is_active'] ?? false,
        ]);

        return to_route('academic.levels.show', $arm->academic_level_id)
            ->with('status', __('Arm updated.'));
    }
}
