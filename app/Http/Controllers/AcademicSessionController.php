<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademicSessionRequest;
use App\Models\AcademicSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The current school's academic sessions. Tenant-scoped: `AcademicSession` is a
 * `BelongsToSchool` model — every query is constrained to the active tenant by
 * `SchoolScope`, `school_id` is stamped on create, and route-model binding of
 * another school's session resolves to 404.
 *
 * Onboarding only needs the *first* session. Term / calendar structure is the
 * Academic Management milestone.
 */
class AcademicSessionController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.academic-sessions', [
            'sessions' => AcademicSession::query()
                ->orderByDesc('starts_on')
                ->paginate(20),
        ]);
    }

    public function store(StoreAcademicSessionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $session = AcademicSession::create([
            'name' => $validated['name'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
        ]);

        // Make it current if asked, or automatically when it is the school's first.
        if (($validated['is_current'] ?? false) || AcademicSession::query()->count() === 1) {
            $session->makeCurrent();
        }

        return to_route('academic-sessions.index')
            ->with('status', __('Academic session ":name" created.', ['name' => $session->name]));
    }

    public function update(int $session): RedirectResponse
    {
        $this->authorize('school.settings.update');

        // Resolved here (not route-model-bound) so it runs after the `tenant`
        // middleware: SchoolScope then constrains the lookup to the active
        // school, and another school's id simply 404s.
        $academicSession = AcademicSession::query()->findOrFail($session);

        // The only mutation onboarding needs: make an existing session current.
        $academicSession->makeCurrent();

        return to_route('academic-sessions.index')
            ->with('status', __('":name" is now the current session.', ['name' => $academicSession->name]));
    }
}
