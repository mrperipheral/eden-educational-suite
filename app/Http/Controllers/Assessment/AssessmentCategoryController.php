<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\CategoryRequest;
use App\Models\AssessmentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Assessment categories — school configuration. Gated `assessment.view` (read)
 * and `assessment.manage` (write), behind `module:assessments`. Categories are
 * `BelongsToSchool`; `{category}` is resolved by tenant-scoped `findOrFail`, so
 * another school's id 404s. Categories are deactivated, not deleted.
 */
class AssessmentCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('assessment.view');

        return view('assessments.categories.index', [
            'categories' => AssessmentCategory::query()
                ->withCount('assessments')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        AssessmentCategory::create($request->payload());

        return to_route('assessments.categories.index')->with('status', __('Category added.'));
    }

    public function update(CategoryRequest $request, int $category): RedirectResponse
    {
        $category = AssessmentCategory::query()->findOrFail($category);
        $category->update($request->payload());

        return to_route('assessments.categories.index')->with('status', __('Category updated.'));
    }
}
