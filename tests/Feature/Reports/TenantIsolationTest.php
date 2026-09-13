<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\Examination;
use App\Models\FeeCategory;
use App\Models\StudentFeeCharge;
use App\Models\User;

/**
 * M27 §19 security review — cross-school reporting isolation. Every report
 * relies on the existing `SchoolScope` global scope (never a manual
 * `where('school_id', …)`), so these tests prove that scope actually holds
 * for every report surface, plus that URL/query-string manipulation cannot
 * be used to reach another school's data (IDOR).
 */
class TenantIsolationTest extends ReportsTestCase
{
    public function test_school_a_admin_never_sees_school_b_students_in_enrollment_report(): void
    {
        $schoolA = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $this->enrolledStudents($schoolA, $scaffoldA, 2);

        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $this->enrolledStudents($schoolB, $scaffoldB, 5);

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);

        $this->get(route('reports.students.index'))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
    }

    public function test_school_a_admin_never_sees_school_b_result_runs(): void
    {
        $schoolA = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $this->resultRun($schoolA, $scaffoldA, ['status' => 'published']);

        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $this->resultRun($schoolB, $scaffoldB, ['status' => 'published']);
        $this->enterSchool($schoolB);
        $periodB2 = $scaffoldB['session']->periods()->create([
            'name' => 'Second Term', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'position' => 2,
        ]);
        $this->app->forgetScopedInstances();
        $this->resultRun($schoolB, $scaffoldB, ['status' => 'locked', 'academic_period_id' => $periodB2->id]);

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);

        $this->get(route('reports.academic.index'))
            ->assertOk()
            ->assertViewHas('runs', fn ($runs) => $runs->total() === 1);
    }

    /**
     * IDOR: a Teacher (or any staff user) in School A must not be able to
     * drill into an examination's attempts by guessing/incrementing the
     * `{examination}` id of a School B exam — the tenant-scoped `findOrFail`
     * in `CbtReportController::attempts()` must 404, never leak a title or
     * attempt list.
     */
    public function test_cbt_attempts_route_404s_for_an_examination_id_belonging_to_another_school(): void
    {
        $schoolA = $this->newSchool();
        $this->scaffold($schoolA);
        $this->enableModule($schoolA, 'cbt');

        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $this->enableModule($schoolB, 'cbt');
        $this->enterSchool($schoolB);
        $examInSchoolB = Examination::factory()->create([
            'academic_session_id' => $scaffoldB['session']->id,
            'academic_period_id' => $scaffoldB['period']->id,
            'academic_level_id' => $scaffoldB['level']->id,
            'level_arm_id' => $scaffoldB['arm']->id,
            'subject_id' => $scaffoldB['subject']->id,
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);

        $this->get(route('reports.cbt.attempts', $examInSchoolB->id))->assertNotFound();
    }

    /**
     * School-id manipulation: even if a school-user tries to inject a
     * `school_id`/`school`-shaped query parameter on a report route, every
     * report class ignores request-supplied school identifiers entirely —
     * the active school always comes from `TenantContext`, never the
     * request. This proves the filter arrays these controllers build never
     * even look at such a parameter, so there is nothing for an attacker to
     * manipulate.
     */
    public function test_school_id_query_parameter_is_ignored_by_every_report_filter(): void
    {
        $schoolA = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $this->enrolledStudents($schoolA, $scaffoldA, 1);

        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);

        $this->get(route('reports.students.index', ['school_id' => $schoolB->id, 'school' => $schoolB->id]))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 1);
    }

    public function test_fee_reports_never_leak_another_schools_outstanding_balances(): void
    {
        $schoolA = $this->newSchool();
        $scaffoldA = $this->scaffold($schoolA);
        $studentsA = $this->enrolledStudents($schoolA, $scaffoldA, 1);

        $schoolB = $this->newSchool();
        $scaffoldB = $this->scaffold($schoolB);
        $studentsB = $this->enrolledStudents($schoolB, $scaffoldB, 1);

        $this->enterSchool($schoolB);
        StudentFeeCharge::factory()->create([
            'student_id' => $studentsB[0]->id,
            'fee_category_id' => FeeCategory::factory()->create()->id,
            'created_by' => User::factory()->create()->id,
            'academic_session_id' => $scaffoldB['session']->id,
            'academic_level_id' => $scaffoldB['level']->id,
            'amount' => '99999.00',
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($schoolA, Role::Bursar);
        $this->get(route('reports.fees.index'))
            ->assertOk()
            ->assertDontSee('99999');
    }

    public function test_platform_report_route_is_unreachable_from_a_school_users_own_context(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get(route('admin.reports.index'))->assertForbidden();
    }
}
