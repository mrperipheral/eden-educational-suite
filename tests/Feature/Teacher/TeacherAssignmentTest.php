<?php

namespace Tests\Feature\Teacher;

use App\Enums\Role;
use App\Enums\TeacherAssignmentStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Support\Facades\DB;

class TeacherAssignmentTest extends TeacherTestCase
{
    private function rowsFor(int $schoolId)
    {
        return TeacherAssignment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    /** @return array<string, mixed> */
    private function payload(array $s, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $s['session']->id,
            'academic_period_id' => $s['period']->id,
            'academic_level_id' => $s['level']->id,
            'level_arm_id' => $s['arm']->id,
            'subject_id' => $s['subject']->id,
            'status' => 'active',
            'started_on' => '2025-09-15',
        ], $overrides);
    }

    public function test_admin_can_assign_a_teacher(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))
            ->assertRedirect(route('teachers.show', $teacher->id));

        $assignment = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($teacher->id, $assignment->teacher_id);
        $this->assertSame($school->id, $assignment->school_id);
        $this->assertSame(TeacherAssignmentStatus::Active, $assignment->status);
        $this->assertSame($scaffold['subject']->id, $assignment->subject_id);
    }

    public function test_assignment_relationships_resolve(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $assignment = $teacher->assignments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->enterSchool($school);

        $fresh = TeacherAssignment::query()->with(['teacher', 'session', 'period', 'level', 'arm', 'subject'])->findOrFail($assignment->id);
        $this->assertTrue($fresh->teacher->is($teacher));
        $this->assertSame($scaffold['session']->id, $fresh->session->id);
        $this->assertSame($scaffold['period']->id, $fresh->period->id);
        $this->assertSame($scaffold['level']->id, $fresh->level->id);
        $this->assertSame($scaffold['arm']->id, $fresh->arm->id);
        $this->assertSame($scaffold['subject']->id, $fresh->subject->id);
    }

    public function test_assignment_can_be_edited_and_ended(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $assignment = $teacher->assignments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/assignments/{$assignment->id}", $this->payload($scaffold, [
            'status' => 'ended', 'ended_on' => '2025-11-01',
        ]))->assertRedirect(route('teachers.show', $teacher->id));

        $this->assertSame(TeacherAssignmentStatus::Ended, $assignment->fresh()->status);
        $this->assertSame('2025-11-01', $assignment->fresh()->ended_on->toDateString());
    }

    public function test_ending_an_assignment_requires_an_end_date(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $assignment = $teacher->assignments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.assignments.edit', $assignment->id))
            ->patch("/teachers/assignments/{$assignment->id}", $this->payload($scaffold, ['status' => 'ended', 'ended_on' => '']))
            ->assertSessionHasErrors('ended_on');
    }

    public function test_assignment_can_be_removed(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $assignment = $teacher->assignments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.show', $teacher->id))
            ->delete("/teachers/assignments/{$assignment->id}")
            ->assertRedirect();

        $this->assertSame(0, $this->rowsFor($school->id)->count());
        $this->assertNotNull($teacher->fresh(), 'the teacher record is kept');
    }

    public function test_historical_assignments_are_preserved(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // A finished assignment, recorded directly as ended.
        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, [
            'status' => 'ended', 'started_on' => '2023-09-15', 'ended_on' => '2024-07-24',
        ]))->assertRedirect();
        // A current one.
        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))->assertRedirect();

        $this->assertSame(2, $this->rowsFor($school->id)->where('teacher_id', $teacher->id)->count());
        $this->assertSame(1, $this->rowsFor($school->id)->where('teacher_id', $teacher->id)->where('status', 'active')->count());
    }

    // -- Duplicate protection ------------------------------------------

    public function test_a_duplicate_active_assignment_is_rejected_but_an_ended_one_is_fine(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))->assertRedirect();

        // Same (teacher, class, subject) again, active -> rejected.
        $this->from(route('teachers.assignments.create', $teacher->id))
            ->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))
            ->assertSessionHasErrors('subject_id');

        // Same tuple but ended -> allowed (history / re-assignment).
        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, [
            'status' => 'ended', 'started_on' => '2023-09-15', 'ended_on' => '2024-07-24',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_a_teacher_may_teach_the_same_subject_to_different_classes(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, ['level_arm_id' => null]))->assertSessionHasNoErrors();
        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, [
            'academic_level_id' => $otherLevel->id, 'level_arm_id' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_editing_an_assignment_does_not_collide_with_itself(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $assignment = $teacher->assignments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/assignments/{$assignment->id}", $this->payload($scaffold, ['started_on' => '2025-09-20']))
            ->assertSessionHasNoErrors();
    }

    // -- Invalid combinations ----------------------------------------

    public function test_an_arm_from_another_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.assignments.create', $teacher->id))
            ->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_period_from_another_session_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-01', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.assignments.create', $teacher->id))
            ->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, ['academic_period_id' => $otherPeriod->id]))
            ->assertSessionHasErrors('academic_period_id');
    }

    public function test_the_subject_is_required_and_validated(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.assignments.create', $teacher->id))
            ->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold, ['subject_id' => '']))
            ->assertSessionHasErrors('subject_id');
    }

    public function test_cross_school_academic_ids_are_rejected_without_leaking(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $teacherB = $this->teacherFor($b);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $response = $this->from(route('teachers.assignments.create', $teacherB->id))
            ->post("/teachers/{$teacherB->id}/assignments", [
                'academic_session_id' => $scaffoldA['session']->id,
                'academic_period_id' => $scaffoldA['period']->id,
                'academic_level_id' => $scaffoldA['level']->id,
                'level_arm_id' => $scaffoldA['arm']->id,
                'subject_id' => $scaffoldA['subject']->id,
                'status' => 'active',
                'started_on' => '2025-09-15',
            ]);

        $response->assertSessionHasErrors([
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id', 'subject_id',
        ]);
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    // -- Authorization + module + isolation -------------------------

    public function test_view_roles_cannot_manage_assignments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);

        foreach ($this->teacherRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/teachers/{$teacher->id}/assignments/create")->assertForbidden();
            $this->from(route('teachers.show', $teacher->id))
                ->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_module_gate_blocks_assignment_routes(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->disableStaff($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/teachers/{$teacher->id}/assignments/create")->assertNotFound();
        $this->post("/teachers/{$teacher->id}/assignments", $this->payload($scaffold))->assertNotFound();
    }

    public function test_assignments_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $scaffoldA = $this->scaffold($a);
        $teacherA = $this->teacherFor($a);
        $this->enterSchool($a);
        $assignmentA = $teacherA->assignments()->create($this->payload($scaffoldA));
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get("/teachers/assignments/{$assignmentA->id}/edit")->assertNotFound();
        $this->patch("/teachers/assignments/{$assignmentA->id}", $this->payload($scaffoldA, ['status' => 'ended', 'ended_on' => '2025-10-01']))
            ->assertNotFound();
        $this->delete("/teachers/assignments/{$assignmentA->id}")->assertNotFound();
        $this->post("/teachers/{$teacherA->id}/assignments", $this->payload($scaffoldA))->assertNotFound();

        $this->assertSame(TeacherAssignmentStatus::Active, $assignmentA->fresh()->status);
        $this->assertSame(1, $this->rowsFor($a->id)->count());
    }

    public function test_assignment_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $teacherA = $this->teacherFor($a);
        $this->enterSchool($a);
        $assignment = $teacherA->assignments()->create($this->payload($scaffoldA));

        $this->expectException(TenantMismatchException::class);
        $assignment->school_id = $b->id;
        $assignment->save();
    }

    // -- Performance ------------------------------------------------

    public function test_teacher_profile_does_not_n_plus_one_on_assignments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        collect(range(1, 6))->each(fn ($i) => $teacher->assignments()->create($this->payload($scaffold, [
            'level_arm_id' => null,
            'subject_id' => Subject::factory()->create()->id,
        ])));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/teachers/{$teacher->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "teacher profile ran {$queries} queries for 6 assignments");
    }
}
