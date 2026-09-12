<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\Role;
use App\Models\School;

/**
 * CSV export — respects the current tenant, authorization and active
 * filters/search; never leaks another school's data (M25,
 * `docs/entry-placement-assessment.md`).
 */
class EntryAssessmentExportTest extends EntryAssessmentTestCase
{
    public function test_export_returns_a_csv_with_the_expected_header_and_rows(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Exportable Candidate', 'score' => 80, 'max_score' => 100]);

        $response = $this->get('/entry-assessments/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Candidate name', $content);
        $this->assertStringContainsString('Exportable Candidate', $content);
        $this->assertStringContainsString('80.00', $content);
    }

    public function test_export_respects_the_current_search_filter(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Included Candidate']);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Excluded Candidate']);

        $content = $this->get('/entry-assessments/export?q=Included')->streamedContent();

        $this->assertStringContainsString('Included Candidate', $content);
        $this->assertStringNotContainsString('Excluded Candidate', $content);
    }

    public function test_export_respects_the_status_filter(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Active One']);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Archived One', 'status' => 'archived']);

        $content = $this->get('/entry-assessments/export?status=active')->streamedContent();

        $this->assertStringContainsString('Active One', $content);
        $this->assertStringNotContainsString('Archived One', $content);
    }

    public function test_export_never_includes_another_schools_records(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->enableEntryAssessment($schoolA);
        $this->enableEntryAssessment($schoolB);
        $contextB = $this->classContext($schoolB);
        $this->assessmentIn($schoolB, $contextB, ['candidate_name' => 'Foreign Candidate']);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);
        $content = $this->get('/entry-assessments/export')->streamedContent();

        $this->assertStringNotContainsString('Foreign Candidate', $content);
    }

    public function test_bursar_cannot_export(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::Bursar);

        $this->get('/entry-assessments/export')->assertForbidden();
    }

    public function test_export_shows_the_assessors_name_not_their_internal_id(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['assessor_id' => $admin->id]);

        $content = $this->get('/entry-assessments/export')->streamedContent();

        $this->assertStringContainsString($admin->name, $content);
    }
}
