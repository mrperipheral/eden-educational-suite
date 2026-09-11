<?php

namespace Tests\Feature\Portal;

use App\Models\Guardian;
use App\Models\Student;

/**
 * M10 remains the source of truth for guardian/student relationships — a
 * parent cannot link/unlink a student, remove another guardian, change a
 * relationship type, or make themselves primary (see `docs/parent-portal.md`
 * §"Guardian link management"). None of these controls exist in the Parent
 * Portal at all; every M10 write route stays gated `guardian.manage`, which a
 * Parent-role account never holds.
 */
class ParentGuardianProtectionTest extends ParentPortalTestCase
{
    public function test_a_parent_cannot_link_a_new_student_to_their_guardian_record(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $otherStudent = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/guardians/students/{$otherStudent->id}/link")->assertForbidden();
    }

    public function test_a_parent_cannot_create_a_guardian_link_by_posting_directly(): void
    {
        $school = $this->newSchool();
        [$user, $guardian] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $otherStudent = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->post('/guardians/links', [
            'student_id' => $otherStudent->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'legal_guardian',
        ])->assertForbidden();
    }

    public function test_a_parent_cannot_remove_a_guardian_link(): void
    {
        $school = $this->newSchool();
        [$user, $guardian, $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $link = $students[0]->guardianLinks()->where('guardian_id', $guardian->id)->firstOrFail();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->delete("/guardians/links/{$link->id}")->assertForbidden();

        $this->assertNotNull($link->fresh());
    }

    public function test_a_parent_cannot_change_the_relationship_type_or_make_themselves_primary(): void
    {
        $school = $this->newSchool();
        [$user, $guardian, $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $link = $students[0]->guardianLinks()->where('guardian_id', $guardian->id)->firstOrFail();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->patch("/guardians/links/{$link->id}", ['relationship' => 'father', 'is_primary' => true])
            ->assertForbidden();
    }

    public function test_a_parent_cannot_link_their_account_to_a_different_guardian_record(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $otherGuardian = Guardian::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->patch("/guardians/{$otherGuardian->id}/user", ['user_id' => $user->id])->assertForbidden();
        $this->assertNull($otherGuardian->fresh()->user_id);
    }
}
