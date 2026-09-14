<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Reports\DashboardReport;
use App\Support\Modules\SchoolModules;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Placeholder authenticated landing page. Tenant-scoped: it only renders
     * once EnforceTenant has resolved the active school. The real, role-aware
     * dashboard is a later milestone — except for a Parent-role member, who is
     * sent straight to their own Parent Portal (M16, `docs/parent-portal.md`),
     * and a Student-role member, sent to their own Student Portal (M17,
     * `docs/student-portal.md`): the admin dashboard has nothing for them.
     */
    public function __invoke(Request $request, TenantContext $tenant, SchoolModules $modules, DashboardReport $dashboardReport): View|RedirectResponse
    {
        $school = $tenant->schoolOrFail();
        $role = $request->user()->roleIn($school);

        if ($role === Role::Parent && $modules->enabled(Module::ParentPortal)) {
            return to_route('parent.dashboard');
        }

        if ($role === Role::Student && $modules->enabled(Module::StudentPortal)) {
            return to_route('student.dashboard');
        }

        return view('dashboard', [
            'school' => $school,
            'onboarding' => $this->onboarding($request, $school),
            'administration' => $this->administration($request, $school, $modules),
            // M27 KPI cards — each card is independently module- and
            // permission-gated inside DashboardReport::kpis(); an empty
            // array here just means "nothing this viewer may see yet",
            // never a misleading zero for a disabled module.
            'kpis' => $modules->enabled(Module::Reports) ? $dashboardReport->kpis($request->user()) : [],
            'todayClasses' => $this->todayClasses($request, $school, $modules),
        ]);
    }

    /**
     * M29.5 — a Teacher's own lessons for today, straight from the Timetable
     * module's own data (no new metric, no new table): the lessons whose
     * `teacher_id` matches this user's linked {@see Teacher} record, on
     * today's weekday (in the school's own timezone), from a *published*
     * timetable only. Two bounded queries (the teacher lookup, the entries
     * themselves) regardless of how many lessons or teachers the school has.
     *
     * @return Collection<int, TimetableEntry>|null null when the module is
     *                                              off, the viewer can't see timetables, or they have no linked
     *                                              teacher record (nothing to show, not an error).
     */
    private function todayClasses(Request $request, School $school, SchoolModules $modules): ?Collection
    {
        if (! $modules->enabled(Module::Timetable) || ! $request->user()->hasPermission(Permission::TimetableView)) {
            return null;
        }

        $teacher = Teacher::query()->where('user_id', $request->user()->id)->first();

        if ($teacher === null) {
            return null;
        }

        $today = now($school->settings?->timezone ?? config('app.timezone'))->dayOfWeek;

        return TimetableEntry::query()
            ->where('teacher_id', $teacher->id)
            ->where('weekday', $today)
            ->whereHas('timetable', fn ($q) => $q->published())
            ->with(['level:id,name', 'arm:id,name', 'subject:id,name'])
            ->orderBy('start_time')
            ->limit(8)
            ->get();
    }

    /**
     * A small, cheap "what's going on administratively" panel (M26,
     * `docs/audit.md`) — only for `audit.view` holders. Every figure here
     * comes from a query already cheap at this scale (a handful of rows per
     * school): the last 5 audit entries, a single grouped member-status
     * count, and the module states `SchoolModules` already memoised for
     * this request. No new expensive aggregation is introduced.
     *
     * @return array{recentActivity: Collection, activeMembers: int, suspendedMembers: int, modulesEnabled: int, modulesTotal: int}|null
     */
    private function administration(Request $request, School $school, SchoolModules $modules): ?array
    {
        if (! $request->user()->hasPermission(Permission::AuditView)) {
            return null;
        }

        $members = $school->users()->get(['users.id', 'users.status']);
        $activeMembers = $members->filter(fn ($u) => $u->status === UserStatus::Active)->count();

        $states = $modules->states();

        return [
            'recentActivity' => AuditLog::query()
                ->where('school_id', $school->id)
                ->ordered()
                ->limit(5)
                ->get(),
            'activeMembers' => $activeMembers,
            'suspendedMembers' => $members->count() - $activeMembers,
            'modulesEnabled' => count(array_filter($states)),
            'modulesTotal' => count($states),
        ];
    }

    /**
     * Onboarding checklist — only for administrators who can act on it.
     *
     * @return array{steps: list<array{label: string, done: bool, route: string}>, complete: bool}|null
     */
    private function onboarding(Request $request, School $school): ?array
    {
        if (! $request->user()->hasPermission(Permission::SchoolSettingsUpdate)) {
            return null;
        }

        $steps = [
            [
                'label' => __('Assign a School Admin'),
                'done' => $school->hasSchoolAdmin(),
                'route' => route('members.create'),
            ],
            [
                'label' => __('Review school settings'),
                'done' => $school->settings()->whereNotNull('completed_at')->exists(),
                'route' => route('settings.school.edit'),
            ],
        ];

        // The academic session step only applies when the Academic module is on
        // for this school (it lives behind `module:academics`).
        if (app(SchoolModules::class)->enabled(Module::Academics)) {
            $steps[] = [
                'label' => __('Create the first academic session'),
                'done' => $school->academicSessions()->exists(),
                'route' => route('academic.sessions.index'),
            ];
        }

        return [
            'steps' => $steps,
            'complete' => collect($steps)->every(fn ($s) => $s['done']),
        ];
    }
}
