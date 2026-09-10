<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignmentTest extends AssessmentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Assignment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_an_assignment_is_created_with_a_completion_roster_and_teacher_ownership(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 4);
        $teacher = $this->actingAsTeacherFor($school, $scaffold);

        $this->post('/assessments/assignments', $this->assignmentPayload($scaffold))->assertRedirect();

        $assignment = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($school->id, $assignment->school_id);
        $this->assertSame(AssignmentStatus::Draft, $assignment->status);
        $this->assertSame($teacher->id, $assignment->created_by);
        $this->assertNotNull($assignment->teacher_id, 'ownership is set from the creating teacher record');
        $this->assertSame(4, $assignment->submissions()->count());
        $this->assertSame(4, $assignment->submissions()->where('status', 'pending')->count());
    }

    public function test_the_due_date_cannot_be_before_the_assigned_date(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/assignments/create')->post('/assessments/assignments', $this->assignmentPayload($scaffold, [
            'assigned_on' => now()->toDateString(),
            'due_on' => now()->subWeek()->toDateString(),
        ]))->assertSessionHasErrors('due_on');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_required_fields_and_academic_context_are_validated(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/assessments/assignments/create')->post('/assessments/assignments', [
            'academic_session_id' => '', 'academic_period_id' => '', 'academic_level_id' => '',
            'level_arm_id' => '', 'subject_id' => '', 'title' => '', 'assigned_on' => '', 'due_on' => '',
        ])->assertSessionHasErrors([
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id', 'subject_id',
            'title', 'assigned_on', 'due_on',
        ]);
    }

    public function test_the_lifecycle_runs_draft_published_closed_and_back(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assignment = $this->assignmentFor($school, $scaffold);
        $this->snapshotSubmissions($school, $assignment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/assessments/assignments/{$assignment->id}/publish")->assertRedirect();
        $this->assertSame(AssignmentStatus::Published, $assignment->fresh()->status);

        $this->post("/assessments/assignments/{$assignment->id}/close")->assertRedirect();
        $this->assertSame(AssignmentStatus::Closed, $assignment->fresh()->status);

        $this->post("/assessments/assignments/{$assignment->id}/reopen")->assertRedirect();
        $this->assertSame(AssignmentStatus::Published, $assignment->fresh()->status);

        $this->post("/assessments/assignments/{$assignment->id}/unpublish")->assertRedirect();
        $this->assertSame(AssignmentStatus::Draft, $assignment->fresh()->status);
    }

    public function test_a_published_assignment_cannot_be_structurally_edited(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assignment = $this->assignmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/assessments/assignments/{$assignment->id}/edit")->assertForbidden();
        $this->patch("/assessments/assignments/{$assignment->id}", [
            'title' => 'X', 'assigned_on' => now()->subDay()->toDateString(), 'due_on' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_completion_can_be_tracked_and_is_length_and_date_validated(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3)->values();
        $assignment = $this->assignmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotSubmissions($school, $assignment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/assignments/{$assignment->id}/submissions", ['submissions' => [
            $students[0]->id => ['status' => 'submitted', 'submitted_on' => now()->subDay()->toDateString()],
            $students[1]->id => ['status' => 'late'],
            $students[2]->id => ['status' => 'exempt', 'remark' => 'On approved leave'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $assignment->submissions()->where('student_id', $students[0]->id)->value('status')->value);
        $this->assertNotNull($assignment->submissions()->whereNotNull('recorded_at')->first());

        $this->from(route('assessments.assignments.submissions.edit', $assignment->id))
            ->patch("/assessments/assignments/{$assignment->id}/submissions", ['submissions' => [
                $students[0]->id => ['status' => 'submitted', 'remark' => str_repeat('x', 501)],
            ]])->assertSessionHasErrors('submissions.'.$students[0]->id.'.remark');

        $this->from(route('assessments.assignments.submissions.edit', $assignment->id))
            ->patch("/assessments/assignments/{$assignment->id}/submissions", ['submissions' => [
                $students[0]->id => ['status' => 'submitted', 'submitted_on' => now()->addWeek()->toDateString()],
            ]])->assertSessionHasErrors('submissions.'.$students[0]->id.'.submitted_on');
    }

    public function test_a_closed_assignment_rejects_completion_edits(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assignment = $this->assignmentFor($school, $scaffold, ['status' => 'closed', 'published_at' => now()]);
        $this->snapshotSubmissions($school, $assignment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/assignments/{$assignment->id}/submissions", [
            'submissions' => [$student->id => ['status' => 'submitted']],
        ])->assertForbidden();
    }

    public function test_an_assignment_with_recorded_submissions_cannot_be_deleted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assignment = $this->assignmentFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->snapshotSubmissions($school, $assignment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/assessments/assignments/{$assignment->id}/submissions", ['submissions' => [$student->id => ['status' => 'submitted']]]);

        $this->delete("/assessments/assignments/{$assignment->id}")
            ->assertRedirect(route('assessments.assignments.show', $assignment->id))
            ->assertSessionHas('error');
        $this->assertNotNull($assignment->fresh());
    }

    public function test_a_teacher_can_only_manage_assignments_for_their_class_and_subject(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);

        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 2]);
        $otherScaffold = [...$scaffold, 'arm' => $otherArm];
        $this->app->forgetScopedInstances();
        $this->enrolledStudents($school, $otherScaffold, 2);

        $this->actingAsTeacherFor($school, $scaffold);
        $this->post('/assessments/assignments', $this->assignmentPayload($scaffold))->assertSessionHasNoErrors();
        $this->from('/assessments/assignments/create')->post('/assessments/assignments', $this->assignmentPayload($otherScaffold))->assertForbidden();
    }

    // -- Tenant isolation ----------------------------------------------

    public function test_assignments_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $assignmentA = $this->assignmentFor($a, $scaffoldA);
        $this->snapshotSubmissions($a, $assignmentA);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/assessments/assignments')->assertOk()->assertViewHas('assignments', fn ($p) => $p->total() === 0);
        $this->get("/assessments/assignments/{$assignmentA->id}")->assertNotFound();
        $this->patch("/assessments/assignments/{$assignmentA->id}", [])->assertNotFound();
        $this->patch("/assessments/assignments/{$assignmentA->id}/submissions", ['submissions' => []])->assertNotFound();
        $this->post("/assessments/assignments/{$assignmentA->id}/publish")->assertNotFound();
        $this->delete("/assessments/assignments/{$assignmentA->id}")->assertNotFound();
    }

    public function test_an_assignment_cannot_be_created_with_another_schools_context(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from('/assessments/assignments/create')->post('/assessments/assignments', $this->assignmentPayload($scaffoldA))
            ->assertSessionHasErrors(['academic_session_id', 'academic_level_id', 'level_arm_id', 'subject_id']);
        $this->assertSame(0, $this->rowsFor($a->id)->count());
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA);
        $assignment = $this->assignmentFor($a, $scaffoldA);
        $this->enterSchool($a);

        $this->expectException(TenantMismatchException::class);
        $assignment->school_id = $b->id;
        $assignment->save();
    }

    public function test_the_list_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        foreach (range(1, 12) as $i) {
            $this->assignmentFor($school, $scaffold, ['due_on' => now()->addDays($i)->toDateString()]);
        }
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/assessments/assignments')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "the assignment list ran {$queries} queries for 12 assignments");
    }

    public function test_the_submissions_table_holds_no_score(): void
    {
        $columns = Schema::getColumnListing('assignment_submissions');
        sort($columns);

        $this->assertSame([
            'assignment_id', 'created_at', 'id', 'recorded_at', 'recorded_by', 'remark',
            'school_id', 'status', 'student_id', 'submitted_on', 'updated_at',
        ], $columns);

        foreach (['score', 'grade', 'mark'] as $forbidden) {
            $this->assertFalse(Schema::hasColumn('assignment_submissions', $forbidden));
        }
    }

    public function test_the_database_prevents_duplicate_submission_rows(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assignment = $this->assignmentFor($school, $scaffold);
        $this->enterSchool($school);
        $assignment->submissions()->create(['student_id' => $student->id]);

        $this->expectException(QueryException::class);
        $assignment->submissions()->create(['student_id' => $student->id]);
    }

    public function test_an_assessment_may_reference_an_assignment_for_the_same_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assignment = $this->assignmentFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/assessments', $this->assessmentPayload($scaffold, ['assignment_id' => $assignment->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($assignment->id, Assessment::query()->withoutGlobalScopes()->where('school_id', $school->id)->value('assignment_id'));
    }

    public function test_an_assessment_cannot_reference_an_assignment_for_a_different_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        $otherArm = $scaffold['level']->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 2]);
        $this->app->forgetScopedInstances();
        $mismatched = $this->assignmentFor($school, [...$scaffold, 'arm' => $otherArm]);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from('/assessments/create')->post('/assessments', $this->assessmentPayload($scaffold, ['assignment_id' => $mismatched->id]))
            ->assertSessionHasErrors('assignment_id');
    }
}
