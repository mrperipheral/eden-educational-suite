<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\EntryAssessmentStatus;
use App\Enums\Role;
use App\Models\School;

/**
 * The record's own retention lifecycle (Active/Archived) — never a hard
 * delete (M25, `docs/entry-placement-assessment.md`).
 */
class EntryAssessmentLifecycleTest extends EntryAssessmentTestCase
{
    public function test_a_record_can_be_archived_via_http(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context);

        $this->post("/entry-assessments/{$assessment->id}/archive")->assertRedirect();

        $this->enterSchool($school);
        $this->assertEquals(EntryAssessmentStatus::Archived, $assessment->fresh()->status);
    }

    public function test_an_archived_record_can_be_restored_via_http(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['status' => 'archived']);

        $this->post("/entry-assessments/{$assessment->id}/restore")->assertRedirect();

        $this->enterSchool($school);
        $this->assertEquals(EntryAssessmentStatus::Active, $assessment->fresh()->status);
    }

    public function test_archiving_never_deletes_the_row(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context);

        $this->post("/entry-assessments/{$assessment->id}/archive");

        $this->enterSchool($school);
        $this->assertDatabaseHas('entry_assessments', ['id' => $assessment->id]);
    }

    public function test_an_archived_record_remains_viewable(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['status' => 'archived', 'candidate_name' => 'Retired Candidate']);

        $this->get("/entry-assessments/{$assessment->id}")->assertOk()->assertSee('Retired Candidate');
    }

    public function test_editing_a_record_never_touches_an_unrelated_records_status(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $archived = $this->assessmentIn($school, $context, ['status' => 'archived']);
        $active = $this->assessmentIn($school, $context);

        $this->patch("/entry-assessments/{$active->id}", $this->payload($context, ['candidate_name' => 'Updated']));

        $this->enterSchool($school);
        $this->assertEquals(EntryAssessmentStatus::Archived, $archived->fresh()->status);
    }
}
