<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\WeightingSchemeRequest;
use App\Models\AssessmentCategory;
use App\Models\ResultWeightingScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Result-weighting schemes — school configuration. Gated `result.view` (read)
 * and `result.manage` (write), behind `module:results`. Schemes are
 * `BelongsToSchool`; `{scheme}` is resolved by tenant-scoped `findOrFail`.
 * Schemes are deactivated, not deleted.
 */
class ResultWeightingSchemeController extends Controller
{
    public function index(): View
    {
        $this->authorize('result.view');

        return view('results.weighting-schemes.index', [
            'schemes' => ResultWeightingScheme::query()->withCount('items')->ordered()->get(),
        ]);
    }

    public function store(WeightingSchemeRequest $request): RedirectResponse
    {
        $scheme = ResultWeightingScheme::create($request->payload());

        return to_route('results.weighting-schemes.show', $scheme)->with('status', __('Weighting scheme created — add its category weights below.'));
    }

    public function show(int $scheme): View
    {
        $this->authorize('result.view');

        $scheme = ResultWeightingScheme::query()
            ->with(['items' => fn ($q) => $q->orderBy('position'), 'items.category'])
            ->findOrFail($scheme);

        return view('results.weighting-schemes.show', [
            'scheme' => $scheme,
            'categories' => AssessmentCategory::query()->active()->ordered()->get(),
        ]);
    }

    public function update(WeightingSchemeRequest $request, int $scheme): RedirectResponse
    {
        $scheme = ResultWeightingScheme::query()->findOrFail($scheme);
        $scheme->update($request->payload());

        return to_route('results.weighting-schemes.show', $scheme)->with('status', __('Weighting scheme updated.'));
    }
}
