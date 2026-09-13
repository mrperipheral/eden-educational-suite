<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\AuditLog;
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

    /**
     * M28 security hardening — CSV/formula-injection regression. A cell
     * whose text starts with `=`, `+`, `-`, or `@` is read as a formula by
     * Excel/Sheets/LibreOffice on open; a school-controlled free-text field
     * (here, a student's name and a graduation note — both genuinely
     * attacker-reachable by any staff member who can edit a student record)
     * must never reach the exported CSV unneutralized. Unlike the test this
     * replaced, this one actually parses the returned CSV row with
     * `str_getcsv()` and asserts the malicious cell was prefixed — not just
     * that the response didn't crash — and exercises an export that
     * genuinely renders the tampered field (the aggregate-only student
     * enrollment export never did).
     */
    public function test_export_csv_rows_neutralize_formula_injection_payloads(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $student = Student::factory()->create([
            'first_name' => '=SUM(1+1)', 'last_name' => 'Attacker', 'status' => 'graduated',
            'graduated_academic_session_id' => $scaffold['session']->id,
            'graduated_at' => now(),
            'graduation_notes' => '+cmd|\'/c calc\'!A0',
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $csv = $this->get(route('reports.promotion.export', ['tab' => 'graduation']))->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines, 'header + exactly the one graduated student');

        $cells = str_getcsv($lines[1]);
        $this->assertStringStartsWith("'=SUM(1+1)", $cells[0], 'the student name cell must be prefixed to defuse the leading =');
        $this->assertStringContainsString('Attacker', $cells[0]);
        $this->assertSame("'+cmd|'/c calc'!A0", $cells[4], 'the graduation-notes cell must be prefixed to defuse the leading +');
    }

    public function test_audit_log_export_neutralizes_formula_injection_in_the_summary_cell(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        AuditLog::query()->create([
            'school_id' => $school->id,
            'actor_id' => null,
            'actor_name' => 'Tester',
            'event' => 'test.formula_injection',
            'summary' => '=HYPERLINK("http://attacker.example")',
        ]);
        $this->app->forgetScopedInstances();

        $csv = $this->get(route('audit-log.export'))->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $row = str_getcsv(end($lines));

        $this->assertSame("'=HYPERLINK(\"http://attacker.example\")", $row[4]);
    }
}
