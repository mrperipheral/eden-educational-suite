<?php

namespace Tests\Feature\Student;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * Linking a Student record to an existing application account (M17 Student
 * Portal foundation, `docs/student-portal.md`) — mirrors M16's
 * `LinkGuardianUserRequest` / M11's `LinkTeacherUserRequest` exactly.
 * `student.manage` only; never creates an account or sends an invitation.
 */
class StudentUserLinkTest extends StudentTestCase
{
    public function test_a_manager_can_link_a_student_to_an_existing_member(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Student);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/students/{$student->id}")
            ->patch("/students/{$student->id}/user", ['user_id' => $member->id])
            ->assertRedirect("/students/{$student->id}");

        $this->assertSame($member->id, $student->fresh()->user_id);
    }

    public function test_a_manager_can_unlink_a_student(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Student);
        $student->user_id = $member->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/students/{$student->id}/user", ['user_id' => ''])->assertRedirect();

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_the_account_must_be_a_member_of_the_active_school(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $stranger = $this->stranger();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/students/{$student->id}")
            ->patch("/students/{$student->id}/user", ['user_id' => $stranger->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_an_account_cannot_be_linked_to_two_students_in_the_same_school(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $first = Student::factory()->create();
        $second = Student::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Student);
        $first->user_id = $member->id;
        $first->save();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/students/{$second->id}")
            ->patch("/students/{$second->id}/user", ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($second->fresh()->user_id);
    }

    public function test_view_only_roles_cannot_link_a_student(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Student);
        $this->app->forgetScopedInstances();

        foreach ($this->studentRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->patch("/students/{$student->id}/user", ['user_id' => $member->id])->assertForbidden();
            $this->flushSession();
        }

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_user_id_is_not_mass_assignable(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Student);

        // Strict models (outside production) throw on a guarded attribute in
        // a mass-assignment payload, rather than silently discarding it.
        $this->expectException(MassAssignmentException::class);

        Student::create([
            'first_name' => 'Test', 'last_name' => 'Student', 'admission_number' => 'STU-MASS-1',
            'user_id' => $member->id,
        ]);
    }

    public function test_linking_is_tenant_isolated(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enterSchool($schoolB);
        $studentB = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->enterSchool($schoolA);
        $memberOfA = User::factory()->create();
        $memberOfA->joinSchool($schoolA, Role::Student);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->patch("/students/{$studentB->id}/user", ['user_id' => $memberOfA->id])->assertNotFound();
    }
}
