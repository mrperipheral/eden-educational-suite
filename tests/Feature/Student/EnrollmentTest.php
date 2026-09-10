<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class EnrollmentTest extends StudentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Enrollment::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    private function student(School $school): Student
    {
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        return $student;
    }

    /** @return array<string, mixed> */
    private function payload(array $s, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $s['session']->id,
            'academic_period_id' => $s['period']->id,
            'academic_level_id' => $s['level']->id,
            'level_arm_id' => $s['arm']->id,
            'status' => 'active',
            'started_on' => '2025-09-15',
        ], $overrides);
    }

    public function test_admin_can_enroll_a_student_and_it_becomes_current(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold))
            ->assertRedirect(route('students.show', $student->id));

        $enrollment = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($school->id, $enrollment->school_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_a_student_has_at_most_one_active_enrollment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold, ['started_on' => '2024-09-15']));

        $this->enterSchool($school);
        $level2 = AcademicLevel::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold, [
            'academic_level_id' => $level2->id, 'level_arm_id' => null, 'started_on' => '2025-09-15',
        ]));

        $active = $this->rowsFor($school->id)->where('student_id', $student->id)->where('status', 'active')->get();
        $this->assertCount(1, $active, 'only the latest placement stays active');
        $this->assertSame($level2->id, $active->first()->academic_level_id);

        $closed = $this->rowsFor($school->id)->where('student_id', $student->id)->where('status', 'completed')->first();
        $this->assertNotNull($closed);
        $this->assertNotNull($closed->ended_on, 'the superseded enrollment gets an end date');
    }

    public function test_historical_enrollments_are_preserved(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // A completed placement, recorded without making it active.
        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold, [
            'status' => 'completed', 'started_on' => '2023-09-15', 'ended_on' => '2024-07-24', 'make_active' => '0',
        ]))->assertRedirect();
        // Then a current one.
        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold, ['started_on' => '2025-09-15']))->assertRedirect();

        $this->assertSame(2, $this->rowsFor($school->id)->where('student_id', $student->id)->count());
        $this->assertSame(1, $this->rowsFor($school->id)->where('student_id', $student->id)->where('status', 'active')->count());
    }

    public function test_enrollment_can_be_edited(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->enterSchool($school);
        $enrollment = $student->enrollments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/students/enrollments/{$enrollment->id}", $this->payload($scaffold, [
            'status' => 'withdrawn', 'ended_on' => '2025-11-01',
        ]))->assertRedirect(route('students.show', $student->id));

        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->fresh()->status);
        $this->assertSame('2025-11-01', $enrollment->fresh()->ended_on->toDateString());
    }

    public function test_relationships_resolve(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->enterSchool($school);
        $enrollment = $student->enrollments()->create($this->payload($scaffold));
        $this->app->forgetScopedInstances();
        $this->enterSchool($school);

        $fresh = Enrollment::query()->with(['student', 'session', 'period', 'level', 'arm'])->findOrFail($enrollment->id);
        $this->assertTrue($fresh->student->is($student));
        $this->assertSame($scaffold['session']->id, $fresh->session->id);
        $this->assertSame($scaffold['period']->id, $fresh->period->id);
        $this->assertSame($scaffold['level']->id, $fresh->level->id);
        $this->assertSame($scaffold['arm']->id, $fresh->arm->id);
        $this->assertTrue($student->currentEnrollment->is($fresh));
    }

    // -- Invalid combinations --------------------------------------------

    public function test_an_arm_from_another_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('students.enrollments.create', $student->id))
            ->post("/students/{$student->id}/enrollments", $this->payload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_period_from_another_session_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);

        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-01', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('students.enrollments.create', $student->id))
            ->post("/students/{$student->id}/enrollments", $this->payload($scaffold, ['academic_period_id' => $otherPeriod->id]))
            ->assertSessionHasErrors('academic_period_id');
    }

    public function test_cross_school_academic_ids_are_rejected_without_leaking(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $studentB = $this->student($b);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        // Every A id swapped in — each must come back "invalid", not a 500 / leak.
        $response = $this->from(route('students.enrollments.create', $studentB->id))
            ->post("/students/{$studentB->id}/enrollments", [
                'academic_session_id' => $scaffoldA['session']->id,
                'academic_period_id' => $scaffoldA['period']->id,
                'academic_level_id' => $scaffoldA['level']->id,
                'level_arm_id' => $scaffoldA['arm']->id,
                'status' => 'active',
                'started_on' => '2025-09-15',
            ]);

        $response->assertSessionHasErrors(['academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id']);
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    // -- Authorization + module + isolation ------------------------------

    public function test_view_roles_cannot_manage_enrollments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);

        foreach ($this->studentRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/students/{$student->id}/enrollments/create")->assertForbidden();
            $this->from(route('students.show', $student->id))
                ->post("/students/{$student->id}/enrollments", $this->payload($scaffold))->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_module_gate_blocks_enrollment_routes(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->student($school);
        $this->disableStudents($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/students/{$student->id}/enrollments/create")->assertNotFound();
        $this->post("/students/{$student->id}/enrollments", $this->payload($scaffold))->assertNotFound();
    }

    public function test_enrollments_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $scaffoldA = $this->scaffold($a);
        $studentA = $this->student($a);
        $this->enterSchool($a);
        $enrollmentA = $studentA->enrollments()->create($this->payload($scaffoldA));
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get("/students/enrollments/{$enrollmentA->id}/edit")->assertNotFound();
        $this->patch("/students/enrollments/{$enrollmentA->id}", $this->payload($scaffoldA, ['status' => 'withdrawn', 'ended_on' => '2025-10-01']))
            ->assertNotFound();
        $this->post("/students/{$studentA->id}/enrollments", $this->payload($scaffoldA))->assertNotFound();

        $this->assertSame(EnrollmentStatus::Active, $enrollmentA->fresh()->status);
        $this->assertSame(1, $this->rowsFor($a->id)->count());
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $studentA = $this->student($a);
        $this->enterSchool($a);
        $enrollment = $studentA->enrollments()->create($this->payload($scaffoldA));

        $this->expectException(TenantMismatchException::class);
        $enrollment->school_id = $b->id;
        $enrollment->save();
    }
}
