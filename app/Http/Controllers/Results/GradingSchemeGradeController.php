<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\GradingSchemeGradeRequest;
use App\Models\GradingScheme;
use App\Models\GradingSchemeGrade;
use Illuminate\Http\RedirectResponse;

/**
 * Grade bands within a grading scheme. Gated `result.manage`, behind
 * `module:results`. `{grade}` is resolved by tenant-scoped `findOrFail`.
 */
class GradingSchemeGradeController extends Controller
{
    public function store(GradingSchemeGradeRequest $request, int $scheme): RedirectResponse
    {
        $scheme = GradingScheme::query()->findOrFail($scheme);
        $scheme->grades()->create($request->payload());

        return to_route('results.grading-schemes.show', $scheme)->with('status', __('Grade band added.'));
    }

    public function update(GradingSchemeGradeRequest $request, int $grade): RedirectResponse
    {
        $grade = GradingSchemeGrade::query()->findOrFail($grade);
        $grade->update($request->payload());

        return to_route('results.grading-schemes.show', $grade->grading_scheme_id)->with('status', __('Grade band updated.'));
    }

    public function destroy(int $grade): RedirectResponse
    {
        $this->authorize('result.manage');

        $grade = GradingSchemeGrade::query()->findOrFail($grade);
        $schemeId = $grade->grading_scheme_id;
        $grade->delete();

        return to_route('results.grading-schemes.show', $schemeId)->with('status', __('Grade band removed.'));
    }
}
