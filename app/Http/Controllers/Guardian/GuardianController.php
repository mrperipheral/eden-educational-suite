<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\GuardianRequest;
use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guardian / parent records. `Guardian` is a `BelongsToSchool` model resolved
 * with tenant-scoped `findOrFail`, so another school's id 404s (the lookup runs
 * after the `tenant` middleware). The whole area is behind `module:guardians`
 * **and** `->can('guardian.view' | 'guardian.manage')`.
 *
 * The student ↔ guardian relationship itself is managed by
 * {@see GuardianLinkController}.
 */
class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('guardian.view');

        $search = trim((string) $request->query('q', ''));

        $guardians = Guardian::query()
            ->search($search)
            ->withCount('students')
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('guardians.index', [
            'guardians' => $guardians,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('guardian.manage');

        return view('guardians.create', ['guardian' => new Guardian]);
    }

    public function store(GuardianRequest $request): RedirectResponse
    {
        $guardian = Guardian::create($request->validated());

        return to_route('guardians.show', $guardian)
            ->with('status', __(':name has been added.', ['name' => $guardian->shortName()]));
    }

    public function show(int $guardian): View
    {
        $this->authorize('guardian.view');

        $guardian = Guardian::query()
            ->with(['studentLinks' => fn ($q) => $q->with([
                'student' => fn ($s) => $s->with(['currentEnrollment' => fn ($e) => $e->with(['level', 'arm'])]),
            ])])
            ->findOrFail($guardian);

        return view('guardians.show', [
            'guardian' => $guardian,
            'relationships' => GuardianRelationship::all(),
        ]);
    }

    public function edit(int $guardian): View
    {
        $this->authorize('guardian.manage');

        return view('guardians.edit', [
            'guardian' => Guardian::query()->findOrFail($guardian),
        ]);
    }

    public function update(GuardianRequest $request, int $guardian): RedirectResponse
    {
        $model = Guardian::query()->findOrFail($guardian);
        $model->update($request->validated());

        return to_route('guardians.show', $model)->with('status', __('Guardian details updated.'));
    }
}
