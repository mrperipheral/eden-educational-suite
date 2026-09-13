<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;

/**
 * M27 §19 security review — role matrix across every report route.
 * `reports.view`/`reports.export` are always composed with the report's own
 * pre-existing domain permission; neither alone unlocks cross-domain data.
 */
class AuthorizationTest extends ReportsTestCase
{
    public function test_school_admin_and_principal_can_open_every_report(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->enableModule($school, 'cbt');
        $this->enableModule($school, 'learning-materials');

        foreach ([Role::SchoolAdmin, Role::Principal] as $role) {
            $this->actingAsMemberOf($school, $role);

            $this->get(route('reports.index'))->assertOk();
            $this->get(route('reports.academic.index'))->assertOk();
            $this->get(route('reports.attendance.index'))->assertOk();
            $this->get(route('reports.fees.index'))->assertOk();
            $this->get(route('reports.students.index'))->assertOk();
            $this->get(route('reports.staff.index'))->assertOk();
            $this->get(route('reports.cbt.index'))->assertOk();
            $this->get(route('reports.promotion.index'))->assertOk();
            $this->get(route('reports.learning-materials.index'))->assertOk();
            $this->get(route('reports.communication.index'))->assertOk();
        }
    }

    public function test_bursar_can_open_fee_reports_but_not_academic_or_attendance_reports(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->enableModule($school, 'cbt');
        $this->actingAsMemberOf($school, Role::Bursar);

        // Bursar also legitimately holds `student.view` (needed for billing
        // — a pre-existing M4 permission grant, not new in M27), so
        // `reports.students.index` is reachable; the boundary this test
        // asserts is academic/attendance/CBT data, which Bursar never holds
        // a permission for.
        $this->get(route('reports.fees.index'))->assertOk();
        $this->get(route('reports.academic.index'))->assertForbidden();
        $this->get(route('reports.attendance.index'))->assertForbidden();
        $this->get(route('reports.cbt.index'))->assertForbidden();
    }

    public function test_teacher_can_open_academic_and_attendance_reports_but_never_fee_reports(): void
    {
        // Teacher's M4 bundle holds broad *view* permissions (result.view,
        // attendance.view, staff.view, promotion.view, cbt.view, …) — the
        // one report area a Teacher never holds any permission for is fees
        // (`fees.report` is School Admin/Principal/Bursar only, nowhere
        // else in this app). Actual data-scoping to a Teacher's own
        // assigned classes/subjects is asserted at the report-class level
        // (see AcademicReportTest/AttendanceReportTest/CbtReportTest), not
        // route authorization.
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::Teacher);

        $this->get(route('reports.academic.index'))->assertOk();
        $this->get(route('reports.attendance.index'))->assertOk();
        $this->get(route('reports.fees.index'))->assertForbidden();
        $this->get(route('reports.fees.export'))->assertForbidden();
    }

    public function test_staff_can_view_but_not_export(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::Staff);

        $this->get(route('reports.academic.index'))->assertOk();
        $this->get(route('reports.academic.export'))->assertForbidden();
    }

    public function test_parent_cannot_access_any_school_wide_report(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Parent);

        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('reports.academic.index'))->assertForbidden();
        $this->get(route('reports.fees.index'))->assertForbidden();
        $this->get(route('reports.students.index'))->assertForbidden();
    }

    public function test_student_cannot_access_any_staff_report(): void
    {
        $school = $this->newSchool();
        $this->enableModule($school, 'cbt');
        $this->actingAsMemberOf($school, Role::Student);

        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('reports.cbt.index'))->assertForbidden();
        $this->get(route('reports.academic.index'))->assertForbidden();
    }

    public function test_role_less_member_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, null);

        $this->get(route('reports.index'))->assertForbidden();
    }

    public function test_only_school_admin_principal_and_bursar_hold_reports_export(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get(route('reports.fees.export'))->assertOk();

        $this->actingAsMemberOf($school, Role::Teacher);
        $this->get(route('reports.academic.export'))->assertForbidden();
    }

    public function test_platform_reports_route_requires_platform_admin(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_school_user_cannot_invoke_platform_reports_even_with_reports_view(): void
    {
        // A School Admin (who holds reports.view within their own school)
        // must still be refused the platform-level report route — that
        // route is gated by SchoolPolicy::viewAny (isPlatformAdmin()), a
        // wholly separate check from any school-scoped permission.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_platform_admin_can_open_platform_reports(): void
    {
        $this->actingAsPlatformAdmin();

        $this->get(route('admin.reports.index'))->assertOk();
    }

    public function test_disabled_reports_module_blocks_every_report_route(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableReports($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('reports.index'))->assertNotFound();
        $this->get(route('reports.academic.index'))->assertNotFound();
    }

    public function test_disabled_domain_module_blocks_that_reports_home_link_but_not_others(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableModule($school, 'fees');

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Fees &amp; collections', false);
    }

    /**
     * M27 §19 checklist item 10: a disabled domain module must not stay
     * reachable through the reporting back door even though the Reports
     * module itself remains on — mirrors every other domain's own routes
     * (`module:fees` gates `/fees` the same way it now gates `/reports/fees`).
     */
    public function test_disabled_domain_module_blocks_its_own_report_route_with_404(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->disableModule($school, 'fees');

        $this->actingAsMemberOf($school, Role::Bursar);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        $this->get(route('reports.fees.index'))->assertNotFound();
        $this->get(route('reports.fees.export'))->assertNotFound();
    }
}
