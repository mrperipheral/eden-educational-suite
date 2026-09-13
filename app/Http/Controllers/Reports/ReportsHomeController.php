<?php

namespace App\Http\Controllers\Reports;

use App\Enums\Module;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Support\Modules\SchoolModules;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Reports hub — lists every report area this user may open, each
 * gated by its own module + domain permission (mirrors how the main nav
 * decides what to show, see `resources/views/components/layouts/
 * authenticated.blade.php`). Gated `reports.view` alone at the route
 * level; individual sections are hidden here (not just at their own
 * routes) so a user never sees a dead link to something they'd be
 * 403'd from anyway. See `docs/reporting.md` §1.
 */
class ReportsHomeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('reports.view');

        $user = $request->user();
        $modules = app(SchoolModules::class);

        $sections = [
            ['route' => 'reports.academic.index', 'label' => __('Academic performance'), 'description' => __('Student, subject and class performance; result-run summaries.'), 'allowed' => $modules->enabled(Module::Results) && $user->can('result.view')],
            ['route' => 'reports.attendance.index', 'label' => __('Attendance'), 'description' => __('Student and class attendance, with a date-based trend.'), 'allowed' => $modules->enabled(Module::Attendance) && $user->can('attendance.view')],
            ['route' => 'reports.fees.index', 'label' => __('Fees & collections'), 'description' => __('Collection summary, outstanding balances, payment activity.'), 'allowed' => $modules->enabled(Module::Fees) && $user->can('fees.report')],
            ['route' => 'reports.students.index', 'label' => __('Student enrollment'), 'description' => __('Enrollment summary by status, level, arm and gender.'), 'allowed' => $modules->enabled(Module::Students) && $user->can('student.view')],
            ['route' => 'reports.staff.index', 'label' => __('Teachers & staff'), 'description' => __('Counts, status breakdown, subject and workload summary.'), 'allowed' => $modules->enabled(Module::Staff) && $user->can('staff.view')],
            ['route' => 'reports.cbt.index', 'label' => __('CBT examinations'), 'description' => __('Examination summary, attempts, pass rates.'), 'allowed' => $modules->enabled(Module::Cbt) && $user->can('cbt.view')],
            ['route' => 'reports.promotion.index', 'label' => __('Promotion & graduation'), 'description' => __('Promotion batch history and graduation history.'), 'allowed' => $modules->enabled(Module::Promotion) && $user->can('promotion.view')],
            ['route' => 'reports.learning-materials.index', 'label' => __('Learning materials'), 'description' => __('Material counts by subject and type, recent uploads.'), 'allowed' => $modules->enabled(Module::LearningMaterials) && $user->can('material.view')],
            ['route' => 'reports.communication.index', 'label' => __('Communication'), 'description' => __('Thread status/category breakdown, announcement activity.'), 'allowed' => $modules->enabled(Module::Notifications) && $user->can('communication.view')],
        ];

        return view('reports.index', [
            'sections' => array_values(array_filter($sections, fn ($s) => $s['allowed'])),
            'canExport' => $user->hasPermission(Permission::ReportsExport),
        ]);
    }
}
