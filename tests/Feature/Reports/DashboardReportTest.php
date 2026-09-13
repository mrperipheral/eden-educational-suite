<?php

namespace Tests\Feature\Reports;

use App\Enums\Module;
use App\Enums\Role;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Reports\DashboardReport;

/**
 * `DashboardReport` (M27 §2) — each KPI card is gated by both its module
 * and the viewer's domain permission; a disabled module or missing
 * permission omits the card entirely rather than showing a misleading zero.
 */
class DashboardReportTest extends ReportsTestCase
{
    public function test_students_card_present_when_module_enabled_and_permission_held(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Student::factory()->count(2)->create(['status' => 'active']);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $kpis = app(DashboardReport::class)->kpis($admin);

        $this->assertArrayHasKey('students', $kpis);
        $this->assertSame(2, $kpis['students']['total']);
    }

    public function test_students_card_absent_when_module_disabled(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => Module::Students->value, 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $kpis = app(DashboardReport::class)->kpis($admin);

        $this->assertArrayNotHasKey('students', $kpis);
    }

    public function test_fees_card_absent_for_teacher_without_fees_report_permission(): void
    {
        $school = $this->newSchool();
        $teacher = $this->actingAsMemberOf($school, Role::Teacher);

        $this->enterSchool($school);
        $kpis = app(DashboardReport::class)->kpis($teacher);

        $this->assertArrayNotHasKey('fees', $kpis);
    }

    public function test_bursar_gets_fees_card_but_not_academic_results_card(): void
    {
        $school = $this->newSchool();
        $bursar = $this->actingAsMemberOf($school, Role::Bursar);

        $this->enterSchool($school);
        $kpis = app(DashboardReport::class)->kpis($bursar);

        $this->assertArrayHasKey('fees', $kpis);
    }
}
