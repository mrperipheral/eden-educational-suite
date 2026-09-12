<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SessionRequest;
use App\Models\AcademicSession;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Academic sessions (years) — the top of the academic structure.
 *
 * Tenant-scoped: `AcademicSession` is a `BelongsToSchool` model, so every query
 * here is constrained to the active school by `SchoolScope`, `school_id` is
 * stamped on create, and a route id belonging to another school resolves to a
 * 404 (`findOrFail` runs *after* the `tenant` middleware). The whole area also
 * sits behind `module:academics`.
 *
 * Gated by `academics.view` (read) / `academics.manage` (write) — see
 * `docs/academic-foundation.md`.
 */
class SessionController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(): View
    {
        $this->authorize('academics.view');

        return view('academic.sessions.index', [
            'sessions' => AcademicSession::query()
                ->withCount('periods')
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function store(SessionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $session = AcademicSession::create([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ]);

        // Make it current if asked, or automatically when it is the school's first.
        if (($data['is_current'] ?? false) || AcademicSession::query()->count() === 1) {
            $session->makeCurrent();
        }

        $this->audit->record(
            event: 'academic_session.created',
            summary: __(':actor created academic session ":name".', ['actor' => request()->user()->name, 'name' => $session->name]),
            auditable: $session,
            auditableLabel: $session->name,
        );

        return to_route('academic.sessions.index')
            ->with('status', __('Academic session ":name" created.', ['name' => $session->name]));
    }

    public function show(int $session): View
    {
        $this->authorize('academics.view');

        $session = AcademicSession::query()->findOrFail($session);

        return view('academic.sessions.show', [
            'session' => $session,
            'periods' => $session->periods()->ordered()->get(),
        ]);
    }

    public function edit(int $session): View
    {
        $this->authorize('academics.manage');

        return view('academic.sessions.edit', [
            'session' => AcademicSession::query()->findOrFail($session),
        ]);
    }

    public function update(SessionRequest $request, int $session): RedirectResponse
    {
        $model = AcademicSession::query()->findOrFail($session);
        $data = $request->validated();

        $model->update([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ]);

        if ($data['is_current'] ?? false) {
            $model->makeCurrent();
        }

        return to_route('academic.sessions.show', $model)
            ->with('status', __('Academic session updated.'));
    }

    public function makeCurrent(int $session): RedirectResponse
    {
        $this->authorize('academics.manage');

        $model = AcademicSession::query()->findOrFail($session);
        $model->makeCurrent();

        $this->audit->record(
            event: 'academic_session.made_current',
            summary: __(':actor made ":name" the current academic session.', ['actor' => request()->user()->name, 'name' => $model->name]),
            auditable: $model,
            auditableLabel: $model->name,
        );

        return back()->with('status', __('":name" is now the current session.', ['name' => $model->name]));
    }
}
