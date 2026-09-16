<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceTenant;
use App\Http\Requests\SelectSchoolRequest;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a user choose which school they are working in. This is the only place
 * the active tenant is selected; {@see EnforceTenant} re-validates the choice on
 * every subsequent request.
 *
 * Not itself tenant-scoped (it runs before a context exists).
 *
 * Only a Platform Admin may *switch* between schools once one is already
 * active this session — see the check in {@see self::store()}. A non-admin
 * with no active school yet (first sign-in, or a member of more than one
 * school with nothing stored) can still land here and pick one; the nav's
 * "Switch" affordance is hidden for them either way once they're in.
 */
class SchoolContextController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $currentSchoolId = $request->session()->get(EnforceTenant::SESSION_KEY);

        // A non-admin already working in a school has nothing to do here —
        // the picker is not a "switch" screen for them.
        if ($currentSchoolId !== null && ! $request->user()->isPlatformAdmin()) {
            return to_route('dashboard');
        }

        $search = trim((string) $request->query('q')) ?: null;

        return view('schools.select', [
            'schools' => $this->schoolsFor($request, $search),
            'search' => $search,
            'currentSchoolId' => $currentSchoolId,
        ]);
    }

    public function store(SelectSchoolRequest $request): RedirectResponse
    {
        $school = School::query()->findOrFail($request->integer('school'));

        $this->authorize('enter', $school);

        // Only a Platform Admin may *switch* — move from an already-active
        // school to a different one. A non-admin's first entry (no school
        // selected yet this session — e.g. a member of more than one school
        // signing in fresh) still works normally; what's blocked is jumping
        // away from a school they're already working in. Enforced here, not
        // only hidden in the UI (the sidebar's "Switch" link/menu item is
        // hidden for non-admins, but this is the actual gate).
        $currentId = $request->session()->get(EnforceTenant::SESSION_KEY);
        if ($currentId !== null && $currentId !== $school->getKey() && ! $request->user()->isPlatformAdmin()) {
            abort(403, __('Only a platform administrator can switch between schools.'));
        }

        $request->session()->put(EnforceTenant::SESSION_KEY, $school->getKey());

        return redirect()->intended(route('dashboard'))
            ->with('status', __('You are now working in :school.', ['school' => $school->name]));
    }

    /**
     * @return LengthAwarePaginator<int, School>
     */
    private function schoolsFor(Request $request, ?string $search): LengthAwarePaginator
    {
        $user = $request->user();

        // Platform admins may enter any school; everyone else only their own.
        $query = $user->isPlatformAdmin()
            ? School::query()
            : $user->schools();

        return $query
            ->when($search, fn ($q) => $q->where('schools.name', 'like', '%'.$search.'%'))
            ->orderBy('schools.name')
            ->paginate(15)
            ->withQueryString();
    }
}
