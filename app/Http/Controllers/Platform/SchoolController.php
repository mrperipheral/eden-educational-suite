<?php

namespace App\Http\Controllers\Platform;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Models\School;
use App\Services\SchoolProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform-level school administration. NOT tenant-scoped — a platform admin
 * provisions schools from outside any school context (SchoolPolicy governs
 * access). School-owned data (settings, sessions, members) is managed by a
 * school's own administrators after entering that school's context.
 */
class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', School::class);

        $search = trim((string) $request->query('q')) ?: null;

        $schools = School::query()
            ->withCount('users')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('platform.schools.index', [
            'schools' => $schools,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', School::class);

        return view('platform.schools.create');
    }

    public function store(StoreSchoolRequest $request, SchoolProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validated();

        $school = $provisioner->provision(
            name: $validated['name'],
            slug: $validated['slug'] ?? null,
        );

        if ($initialAdmin = $request->initialAdmin()) {
            // The initial admin is always seated as School Admin. Assert the M4
            // escalation rule against the freshly-created school — a platform
            // admin passes (their authority in any school is total, M4 §5); a
            // non-platform-admin who somehow reached here would not.
            abort_unless($request->user()->canGrantRole(Role::SchoolAdmin, $school), 403);

            $initialAdmin->joinSchool($school, Role::SchoolAdmin);
        }

        return redirect()
            ->route('admin.schools.show', $school)
            ->with('status', __('":name" has been created.', ['name' => $school->name]));
    }

    public function show(School $school): View
    {
        $this->authorize('view', $school);

        $school->loadCount('users');

        return view('platform.schools.show', [
            'school' => $school,
            'hasSchoolAdmin' => $school->hasSchoolAdmin(),
        ]);
    }
}
