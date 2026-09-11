<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\CategoryRequest;
use App\Models\FeeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Fee categories — school configuration. Gated `fees.view` (read) and
 * `fees.manage` (write), behind `module:fees`. Categories are
 * `BelongsToSchool`; `{category}` is resolved by tenant-scoped `findOrFail`,
 * so another school's id 404s. Categories are deactivated, not deleted.
 * Mirrors `App\Http\Controllers\Assessment\AssessmentCategoryController` (M14).
 */
class FeeCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('fees.manage');

        return view('fees.categories.index', [
            'categories' => FeeCategory::query()
                ->withCount('structures')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        FeeCategory::create($request->payload());

        return to_route('fees.categories.index')->with('status', __('Category added.'));
    }

    public function update(CategoryRequest $request, int $category): RedirectResponse
    {
        $category = FeeCategory::query()->findOrFail($category);
        $category->update($request->payload());

        return to_route('fees.categories.index')->with('status', __('Category updated.'));
    }
}
