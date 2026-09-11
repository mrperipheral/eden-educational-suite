<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Models\AssessmentScore;

class AssessmentLifecycleTest extends AssessmentTestCase
{
    public function test_a_draft_publishes_then_locks(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/assessments/{$assessment->id}/publish")->assertRedirect(route('assessments.show', $assessment->id));
        $this->assertSame(AssessmentStatus::Published, $assessment->fresh()->status);
        $this->assertNotNull($assessment->fresh()->published_at);

        $this->post("/assessments/{$assessment->id}/lock")->assertRedirect();
        $fresh = $assessment->fresh();
        $this->assertSame(AssessmentStatus::Locked, $fresh->status);
        $this->assertNotNull($fresh->locked_at);
        $this->assertSame($admin->id, $fresh->locked_by);
    }

    public function test_a_published_assessment_can_be_returned_to_draft(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/assessments/{$assessment->id}/unpublish")->assertRedirect();
        $fresh = $assessment->fresh();
        $this->assertSame(AssessmentStatus::Draft, $fresh->status);
        $this->assertNull($fresh->published_at);
    }

    public function test_the_structure_cannot_be_edited_once_published(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/assessments/{$assessment->id}/edit")->assertForbidden();
        $this->patch("/assessments/{$assessment->id}", [
            'assessment_category_id' => $scaffold['category']->id, 'title' => 'X', 'max_score' => 5,
        ])->assertForbidden();
    }

    public function test_scores_can_still_be_entered_while_published(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/assessments/{$assessment->id}/scores")->assertOk();
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 12]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('12.00', $assessment->scores()->where('student_id', $student->id)->value('score'));
    }

    public function test_only_a_manager_can_unlock_a_locked_assessment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'locked', 'published_at' => now(), 'locked_at' => now()]);
        $this->snapshotRoster($school, $assessment);

        // A teacher (record, not manage) cannot unlock.
        $this->actingAsTeacherFor($school, $scaffold);
        $this->post("/assessments/{$assessment->id}/unlock")->assertForbidden();
        $this->assertSame(AssessmentStatus::Locked, $assessment->fresh()->status);

        // The principal can — corrections become possible again.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Principal);
        $this->post("/assessments/{$assessment->id}/unlock")->assertRedirect();
        $fresh = $assessment->fresh();
        $this->assertSame(AssessmentStatus::Published, $fresh->status);
        $this->assertNull($fresh->locked_at);
    }

    public function test_a_locked_assessment_cannot_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'locked', 'published_at' => now(), 'locked_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/assessments/{$assessment->id}")
            ->assertRedirect(route('assessments.show', $assessment->id))
            ->assertSessionHas('error');

        $this->assertNotNull($assessment->fresh());
    }

    public function test_an_assessment_with_recorded_scores_cannot_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 5]]]);

        $this->delete("/assessments/{$assessment->id}")
            ->assertRedirect(route('assessments.show', $assessment->id))
            ->assertSessionHas('error');
        $this->assertNotNull($assessment->fresh());
    }

    public function test_a_draft_assessment_with_no_scores_can_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/assessments/{$assessment->id}")->assertRedirect(route('assessments.index'));

        $this->assertNull($assessment->fresh());
        $this->assertSame(0, AssessmentScore::query()->withoutGlobalScopes()->where('assessment_id', $assessment->id)->count());
    }

    public function test_max_score_cannot_be_dropped_below_a_recorded_score(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold, ['max_score' => 20]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 15]]]);

        $this->from("/assessments/{$assessment->id}/edit")->patch("/assessments/{$assessment->id}", [
            'assessment_category_id' => $scaffold['category']->id, 'title' => 'X', 'max_score' => 10,
        ])->assertSessionHasErrors('max_score');
    }

    public function test_max_score_is_frozen_once_a_score_is_recorded_in_either_direction(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold, ['max_score' => 20]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 12]]]);

        $edit = fn (int|string $max) => $this->from("/assessments/{$assessment->id}/edit")
            ->patch("/assessments/{$assessment->id}", [
                'assessment_category_id' => $scaffold['category']->id, 'title' => 'Renamed', 'max_score' => $max,
            ]);

        // Raising it is rejected — a 12/20 must not silently become 12/50.
        $edit(50)->assertSessionHasErrors('max_score');
        // Lowering it (still above the recorded score) is also rejected now.
        $edit(15)->assertSessionHasErrors('max_score');
        $this->assertSame('20.00', $assessment->fresh()->max_score);

        // Leaving max unchanged still lets the rest of the structure be edited.
        $edit(20)->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $assessment->fresh()->title);

        // Clearing the score frees the maximum again.
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => '']]]);
        $edit(50)->assertSessionHasNoErrors();
        $this->assertSame('50.00', $assessment->fresh()->max_score);
    }
}
