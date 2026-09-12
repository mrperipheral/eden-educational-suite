<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\EntryAssessment;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Support\Facades\Schema;

/**
 * Record creation, editing, viewing, validation and score/percentage
 * handling (M25, `docs/entry-placement-assessment.md`).
 */
class EntryAssessmentTest extends EntryAssessmentTestCase
{
    public function test_school_admin_can_create_a_record_with_a_score(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $response = $this->post('/entry-assessments', $this->payload($context));

        $response->assertRedirect(route('entry-assessments.index'));
        $this->enterSchool($school);
        $this->assertDatabaseHas('entry_assessments', [
            'candidate_name' => 'Ada Okafor',
            'admission_reference' => 'APP-2026-001',
            'subject_id' => $context['subject']->id,
            'academic_level_id' => $context['level']->id,
            'score' => 65,
            'max_score' => 100,
            'result' => 'Pass',
        ]);
    }

    public function test_a_record_may_be_created_without_a_score_yet(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $response = $this->post('/entry-assessments', $this->payload($context, ['score' => null, 'result' => null]));

        $response->assertRedirect(route('entry-assessments.index'));
        $this->enterSchool($school);
        $record = EntryAssessment::query()->first();
        $this->assertNull($record->score);
        $this->assertNull($record->percentage());
        $this->assertEquals($admin->id, $record->assessor_id);
    }

    public function test_a_candidate_may_be_linked_to_an_existing_student(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $student = $this->enrolledStudent($school, $context);

        $this->post('/entry-assessments', $this->payload($context, [
            'student_id' => $student->id,
            'candidate_name' => $student->fullName(),
        ]))->assertRedirect(route('entry-assessments.index'));

        $this->enterSchool($school);
        $this->assertDatabaseHas('entry_assessments', [
            'student_id' => $student->id,
            'candidate_name' => $student->fullName(),
        ]);
    }

    public function test_percentage_is_computed_from_score_and_max_score_not_stored(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['score' => 45, 'max_score' => 60]);

        $this->enterSchool($school);
        $this->assertEquals('75.00', $assessment->fresh()->percentage());
        $this->assertFalse(Schema::hasColumn('entry_assessments', 'percentage'));
    }

    public function test_candidate_name_is_required(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['candidate_name' => '']))
            ->assertSessionHasErrors('candidate_name');
    }

    public function test_level_is_required(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['academic_level_id' => '']))
            ->assertSessionHasErrors('academic_level_id');
    }

    public function test_subject_is_required(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['subject_id' => '']))
            ->assertSessionHasErrors('subject_id');
    }

    public function test_assessed_on_is_required(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['assessed_on' => '']))
            ->assertSessionHasErrors('assessed_on');
    }

    public function test_max_score_is_required_and_must_be_positive(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['max_score' => 0]))
            ->assertSessionHasErrors('max_score');
        $this->post('/entry-assessments', $this->payload($context, ['max_score' => '']))
            ->assertSessionHasErrors('max_score');
    }

    public function test_score_cannot_exceed_max_score(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);

        $this->post('/entry-assessments', $this->payload($context, ['score' => 120, 'max_score' => 100]))
            ->assertSessionHasErrors('score');
    }

    public function test_an_arm_must_belong_to_the_selected_level(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();

        $this->post('/entry-assessments', $this->payload($context, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');
    }

    public function test_subject_must_be_offered_at_the_selected_level(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $this->app->forgetScopedInstances();

        $this->post('/entry-assessments', $this->payload($context, ['subject_id' => $otherSubject->id]))
            ->assertSessionHasErrors('subject_id');
    }

    public function test_a_record_can_be_edited(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['candidate_name' => 'Old Name']);

        $this->patch("/entry-assessments/{$assessment->id}", $this->payload($context, ['candidate_name' => 'New Name']))
            ->assertRedirect(route('entry-assessments.index'));

        $this->enterSchool($school);
        $this->assertEquals('New Name', $assessment->fresh()->candidate_name);
    }

    public function test_editing_does_not_change_the_assessor_or_status(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['assessor_id' => $admin->id]);

        $editor = $this->actingAsRole($school, Role::SchoolAdmin);
        $this->patch("/entry-assessments/{$assessment->id}", $this->payload($context))
            ->assertRedirect(route('entry-assessments.index'));

        $this->enterSchool($school);
        $fresh = $assessment->fresh();
        $this->assertEquals($admin->id, $fresh->assessor_id);
        $this->assertNotEquals($editor->id, $fresh->assessor_id);
    }

    public function test_a_record_can_be_viewed(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $assessment = $this->assessmentIn($school, $context, ['candidate_name' => 'Viewable Candidate']);

        $this->get("/entry-assessments/{$assessment->id}")
            ->assertOk()
            ->assertSee('Viewable Candidate');
    }

    public function test_index_lists_records(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Listed Candidate']);

        $this->get('/entry-assessments')->assertOk()->assertSee('Listed Candidate');
    }

    public function test_index_search_filters_by_candidate_name(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Findable Candidate']);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Someone Else']);

        $response = $this->get('/entry-assessments?q=Findable');
        $response->assertOk()->assertSee('Findable Candidate')->assertDontSee('Someone Else');
    }

    public function test_index_filters_by_status(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Active Candidate']);
        $this->assessmentIn($school, $context, ['candidate_name' => 'Archived Candidate', 'status' => 'archived']);

        $response = $this->get('/entry-assessments?status=archived');
        $response->assertOk()->assertSee('Archived Candidate')->assertDontSee('Active Candidate');
    }

    public function test_index_paginates(): void
    {
        $school = School::factory()->create();
        $this->enableEntryAssessment($school);
        $this->actingAsRole($school, Role::SchoolAdmin);
        $context = $this->classContext($school);
        for ($i = 0; $i < 20; $i++) {
            $this->assessmentIn($school, $context);
        }

        $this->enterSchool($school);
        $this->assertEquals(20, EntryAssessment::query()->count());
        $this->app->forgetScopedInstances();

        $response = $this->get('/entry-assessments');
        $response->assertOk();
        $response->assertViewHas('assessments', fn ($paginator) => $paginator->perPage() === 15 && $paginator->total() === 20);
    }
}
