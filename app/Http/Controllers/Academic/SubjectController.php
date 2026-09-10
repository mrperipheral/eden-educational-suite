<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * School subjects. `Subject` is a `BelongsToSchool` model resolved with
 * tenant-scoped `findOrFail`.
 *
 * Gated by `academics.view` / `academics.manage`; behind `module:academics`.
 */
class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('academics.view');

        $search = trim((string) $request->query('q', ''));

        $subjects = Subject::query()
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->ordered()
            ->paginate(30)
            ->withQueryString();

        return view('academic.subjects.index', [
            'subjects' => $subjects,
            'search' => $search,
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());

        return to_route('academic.subjects.index')
            ->with('status', __('Subject ":name" created.', ['name' => $subject->name]));
    }

    public function edit(int $subject): View
    {
        $this->authorize('academics.manage');

        return view('academic.subjects.edit', [
            'subject' => Subject::query()->findOrFail($subject),
        ]);
    }

    public function update(SubjectRequest $request, int $subject): RedirectResponse
    {
        $model = Subject::query()->findOrFail($subject);

        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? false;
        $model->update($data);

        return to_route('academic.subjects.index')->with('status', __('Subject updated.'));
    }
}
