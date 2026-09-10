<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Enums\Permission;
use App\Models\School;
use App\Support\Modules\SchoolModules;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Placeholder authenticated landing page. Tenant-scoped: it only renders
     * once EnforceTenant has resolved the active school. The real, role-aware
     * dashboard is a later milestone.
     */
    public function __invoke(Request $request, TenantContext $tenant): View
    {
        $school = $tenant->schoolOrFail();

        return view('dashboard', [
            'school' => $school,
            'onboarding' => $this->onboarding($request, $school),
        ]);
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
