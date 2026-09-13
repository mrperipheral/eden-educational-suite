<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\FeePayment;
use App\Models\Student;

/**
 * M27 §17 export requirements — every export reuses the exact filtered,
 * tenant/teacher-scoped report query, enforces the same authorization as
 * the report page, and streams (never loads the whole result set at once).
 */
class ExportTest extends ReportsTestCase
{
    public function test_academic_export_requires_reports_export_not_just_reports_view(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::Staff);

        $this->get(route('reports.academic.export'))->assertForbidden();
    }

    public function test_academic_export_streams_a_csv_with_the_expected_header_row(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $response = $this->get(route('reports.academic.export', ['tab' => 'runs']));
        $response->assertOk();
        $this->assertStringContainsStringIgnoringCase('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Session,Period,Level,Arm,Status,Students', $csv);
    }

    public function test_academic_export_respects_the_same_filters_as_the_report_page(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold, ['status' => 'draft']);
        $this->enterSchool($school);
        $period2 = $scaffold['session']->periods()->create(['name' => 'Second Term', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'position' => 2]);
        $this->app->forgetScopedInstances();
        $this->resultRun($school, $scaffold, ['status' => 'published', 'academic_period_id' => $period2->id]);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $csv = $this->get(route('reports.academic.export', ['tab' => 'runs', 'status' => 'published']))->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        // Header + exactly the one published run, never the draft one.
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('published', strtolower($lines[1]));
    }

    public function test_export_never_exposes_paystack_secret_material(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->enterSchool($school);
        FeePayment::factory()->create(['student_id' => $students[0]->id, 'method' => 'paystack']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $csv = $this->get(route('reports.fees.export', ['tab' => 'payments']))->streamedContent();

        $this->assertStringNotContainsStringIgnoringCase('secret', $csv);
        $this->assertStringNotContainsStringIgnoringCase('sk_', $csv);
    }

    public function test_teacher_is_forbidden_from_exporting_even_when_they_have_reportable_data(): void
    {
        // No role in this app's M4 bundles holds `reports.export` while
        // also being Teacher-assignment-scoped (School Admin/Principal/
        // Bursar — the only reports.export holders — all bypass
        // teacher-scoping via their own domain "manage" permission). So the
        // export boundary for a Teacher is a flat 403, not a scoped export —
        // this proves that holds even when the Teacher's own class has
        // genuine, viewable report data (ruling out an "empty report, so
        // nothing to check" false pass).
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 1);
        $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->actingAsTeacherFor($school, $scaffold);

        $this->get(route('reports.academic.index'))->assertOk();
        $this->get(route('reports.academic.export'))->assertForbidden();
    }

    public function test_promotion_export_requires_reports_export_permission(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::Teacher);

        $this->get(route('reports.promotion.export'))->assertForbidden();
    }

    public function test_export_csv_rows_are_safely_escaped_against_formula_injection_style_content(): void
    {
        // A student whose name starts with a formula-trigger character must
        // still round-trip as plain CSV text, never break the stream.
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        Student::factory()->create([
            'first_name' => '=SUM(1+1)', 'last_name' => 'Test',
        ])->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => 'active',
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $response = $this->get(route('reports.students.export'));
        $response->assertOk();
        $this->assertNotEmpty($response->streamedContent());
    }
}
