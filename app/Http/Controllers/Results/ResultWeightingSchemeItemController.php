<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\WeightingSchemeItemRequest;
use App\Models\ResultWeightingScheme;
use App\Models\ResultWeightingSchemeItem;
use Illuminate\Http\RedirectResponse;

/**
 * Category weights within a weighting scheme. Gated `result.manage`, behind
 * `module:results`. `{item}` is resolved by tenant-scoped `findOrFail`.
 */
class ResultWeightingSchemeItemController extends Controller
{
    public function store(WeightingSchemeItemRequest $request, int $scheme): RedirectResponse
    {
        $scheme = ResultWeightingScheme::query()->findOrFail($scheme);
        $scheme->items()->create($request->payload());

        return to_route('results.weighting-schemes.show', $scheme)->with('status', __('Category weight added.'));
    }

    public function update(WeightingSchemeItemRequest $request, int $item): RedirectResponse
    {
        $item = ResultWeightingSchemeItem::query()->findOrFail($item);
        $item->update($request->payload());

        return to_route('results.weighting-schemes.show', $item->result_weighting_scheme_id)->with('status', __('Category weight updated.'));
    }

    public function destroy(int $item): RedirectResponse
    {
        $this->authorize('result.manage');

        $item = ResultWeightingSchemeItem::query()->findOrFail($item);
        $schemeId = $item->result_weighting_scheme_id;
        $item->delete();

        return to_route('results.weighting-schemes.show', $schemeId)->with('status', __('Category weight removed.'));
    }
}
