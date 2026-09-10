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
 */
class SchoolContextController extends Controller
{
    public function create(Request $request): View
    {
        $search = trim((string) $request->query('q')) ?: null;

        return view('schools.select', [
            'schools' => $this->schoolsFor($request, $search),
            'search' => $search,
            'currentSchoolId' => $request->session()->get(EnforceTenant::SESSION_KEY),
        ]);
    }

    public function store(SelectSchoolRequest $request): RedirectResponse
    {
        $school = School::query()->findOrFail($request->integer('school'));

        $this->authorize('enter', $school);

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
