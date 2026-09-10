<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Subject;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class AssessmentTest extends AssessmentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Assessment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_an_assessment_is_created_and_the_roster_is_snapshotted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 4);
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/assessments', $this->assessmentPayload($scaffold))->assertRedirect();

        $assessment = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($school->id, $assessment->school_id);
        $this->assertSame(AssessmentStatus::Draft, $assessment->status);
        $this->assertSame($admin->id, $assessment->created_by);
        $this->assertSame(4, $assessment->scores()->count());
        $this->assertSame(4, $assessment->scores()->whereNull('score')->count());
    }

    public function test_required_fields_are_validated(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')->post('/assessments', [
            'academic_session_id' => '', 'academic_period_id' => '', 'academic_level_id' => '',
            'level_arm_id' => '', 'subject_id' => '', 'assessment_category_id' => '',
            'title' => '', 'assessment_date' => '', 'max_score' => '',
        ])->assertSessionHasErrors([
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id',
            'subject_id', 'assessment_category_id', 'title', 'assessment_date', 'max_score',
        ]);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_max_score_must_be_positive_with_at_most_two_decimals(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold, ['max_score' => 0]))
            ->assertSessionHasErrors('max_score');
        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold, ['max_score' => -5]))
            ->assertSessionHasErrors('max_score');
        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold, ['max_score' => '10.005']))
            ->assertSessionHasErrors('max_score');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_date_outside_the_session_or_term_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_date' => now()->subYears(2)->toDateString()]))
            ->assertSessionHasErrors('assessment_date');

        // Inside the session but after the term ends.
        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_date' => now()->addMonths(4)->toDateString()]))
            ->assertSessionHasErrors('assessment_date');
    }

    public function test_a_period_from_another_session_and_an_arm_from_another_level_are_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2020-01-01', 'ends_on' => '2020-04-01', 'position' => 1]);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['academic_period_id' => $otherPeriod->id]))
            ->assertSessionHasErrors('academic_period_id');

        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');
    }

    public function test_a_subject_not_offered_by_the_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();   // exists, but not linked to the level
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['subject_id' => $otherSubject->id]))
            ->assertSessionHasErrors('subject_id');
    }

    public function test_an_inactive_category_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->enterSchool($school);
        $inactive = AssessmentCategory::factory()->inactive()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')
            ->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_category_id' => $inactive->id]))
            ->assertSessionHasErrors('assessment_category_id');
    }

    public function test_a_class_with_no_enrolled_students_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);   // no students
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold))
            ->assertSessionHasErrors('level_arm_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_multiple_assessments_of_the_same_category_on_different_dates_are_allowed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_date' => now()->subDays(2)->toDateString()]))->assertSessionHasNoErrors();
        $this->post('/assessments', $this->assessmentPayload($scaffold, ['assessment_date' => now()->subDays(3)->toDateString()]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_the_list_filters_by_class_subject_category_and_status(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->assessmentFor($school, $scaffold);
        $this->assessmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now(), 'assessment_date' => now()->subDays(2)->toDateString()]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/assessments')->assertOk()
            ->assertViewHas('assessments', fn ($p) => $p->total() === 2 && $p->perPage() === 20);
        $this->get('/assessments?status=published')->assertOk()
            ->assertViewHas('assessments', fn ($p) => $p->total() === 1);
        $this->get('/assessments?category='.$scaffold['category']->id)->assertOk()
            ->assertViewHas('assessments', fn ($p) => $p->total() === 2);
    }

    public function test_the_academic_context_cannot_be_changed_after_creation(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}", [
            'assessment_category_id' => $scaffold['category']->id,
            'title' => 'Renamed',
            'max_score' => 20,
            'level_arm_id' => $otherArm->id,   // ignored
            'assessment_date' => now()->subDays(5)->toDateString(),   // ignored
        ])->assertRedirect();

        $fresh = $assessment->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertSame($scaffold['arm']->id, $fresh->level_arm_id);
    }

    // -- Tenant isolation -------------------------------------------------

    public function test_assessments_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $assessmentA = $this->assessmentFor($a, $scaffoldA);
        $this->snapshotRoster($a, $assessmentA);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/assessments')->assertOk()->assertViewHas('assessments', fn ($p) => $p->total() === 0);
        $this->get("/assessments/{$assessmentA->id}")->assertNotFound();
        $this->get("/assessments/{$assessmentA->id}/edit")->assertNotFound();
        $this->patch("/assessments/{$assessmentA->id}", [])->assertNotFound();
        $this->get("/assessments/{$assessmentA->id}/scores")->assertNotFound();
        $this->patch("/assessments/{$assessmentA->id}/scores", ['scores' => []])->assertNotFound();
        $this->post("/assessments/{$assessmentA->id}/publish")->assertNotFound();
        $this->post("/assessments/{$assessmentA->id}/lock")->assertNotFound();
        $this->post("/assessments/{$assessmentA->id}/unlock")->assertNotFound();
        $this->delete("/assessments/{$assessmentA->id}")->assertNotFound();
    }

    public function test_an_assessment_cannot_be_created_with_another_schools_context(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffoldA))
            ->assertSessionHasErrors(['academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id', 'subject_id', 'assessment_category_id']);

        $this->assertSame(0, $this->rowsFor($a->id)->count());
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_school_ownership_is_immutable_and_not_spoofable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/assessments', $this->assessmentPayload($scaffoldA, ['school_id' => $b->id]))->assertRedirect();
        $assessment = $this->rowsFor($a->id)->firstOrFail();
        $this->assertSame($a->id, $assessment->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());

        $this->expectException(TenantMismatchException::class);
        $assessment->school_id = $b->id;
        $assessment->save();
    }

    public function test_a_score_cannot_be_stamped_for_another_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA);
        $assessmentA = $this->assessmentFor($a, $scaffoldA);
        $this->snapshotRoster($a, $assessmentA);
        $this->enterSchool($a);
        $score = $assessmentA->scores()->first();

        $this->expectException(TenantMismatchException::class);
        $score->school_id = $b->id;
        $score->save();
    }
}
