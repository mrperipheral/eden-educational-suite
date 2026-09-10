<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\PeriodRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Academic periods (terms / semesters) within a session. Both the session and
 * the period are `BelongsToSchool` and resolved with tenant-scoped
 * `findOrFail`, so another school's ids 404. A period created through
 * `$session->periods()->create()` gets its `academic_session_id` from the
 * (already tenant-checked) session and its `school_id` from the tenant context.
 *
 * Gated by `academics.manage`; the area sits behind `module:academics`.
 */
class PeriodController extends Controller
{
    public function store(PeriodRequest $request, int $session): RedirectResponse
    {
        $session = AcademicSession::query()->findOrFail($session);
        $data = $request->validated();

        $period = $session->periods()->create([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'position' => $data['position'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($session->periods()->count() === 1) {
            $period->makeCurrent();
        }

        return to_route('academic.sessions.show', $session)
            ->with('status', __('Period ":name" added.', ['name' => $period->name]));
    }

    public function edit(int $period): View
    {
        $this->authorize('academics.manage');

        $period = AcademicPeriod::query()->with('session')->findOrFail($period);

        return view('academic.periods.edit', ['period' => $period]);
    }

    public function update(PeriodRequest $request, int $period): RedirectResponse
    {
        $period = AcademicPeriod::query()->findOrFail($period);
        $data = $request->validated();

        $period->update([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'position' => $data['position'],
            'is_active' => $data['is_active'] ?? false,
        ]);

        return to_route('academic.sessions.show', $period->academic_session_id)
            ->with('status', __('Period updated.'));
    }

    public function makeCurrent(int $period): RedirectResponse
    {
        $this->authorize('academics.manage');

        $period = AcademicPeriod::query()->findOrFail($period);
        $period->makeCurrent();

        return back()->with('status', __('":name" is now the current period.', ['name' => $period->name]));
    }
}
