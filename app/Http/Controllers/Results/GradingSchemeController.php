<?php

namespace App\Http\Controllers\Results;

use App\Http\Controllers\Controller;
use App\Http\Requests\Results\GradingSchemeRequest;
use App\Models\GradingScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Grading schemes — school configuration. Gated `result.view` (read) and
 * `result.manage` (write), behind `module:results`. Schemes are
 * `BelongsToSchool`; `{scheme}` is resolved by tenant-scoped `findOrFail`, so
 * another school's id 404s. Schemes are deactivated, not deleted.
 */
class GradingSchemeController extends Controller
{
    public function index(): View
    {
        $this->authorize('result.view');

        return view('results.grading-schemes.index', [
            'schemes' => GradingScheme::query()->withCount('grades')->ordered()->get(),
        ]);
    }

    public function store(GradingSchemeRequest $request): RedirectResponse
    {
        $scheme = GradingScheme::create($request->payload());

        return to_route('results.grading-schemes.show', $scheme)->with('status', __('Grading scheme created — add its grade bands below.'));
    }

    public function show(int $scheme): View
    {
        $this->authorize('result.view');

        $scheme = GradingScheme::query()->with(['grades' => fn ($q) => $q->ordered()])->findOrFail($scheme);

        return view('results.grading-schemes.show', ['scheme' => $scheme]);
    }

    public function update(GradingSchemeRequest $request, int $scheme): RedirectResponse
    {
        $scheme = GradingScheme::query()->findOrFail($scheme);
        $scheme->update($request->payload());

        return to_route('results.grading-schemes.show', $scheme)->with('status', __('Grading scheme updated.'));
    }
}
