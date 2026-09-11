<?php

namespace Tests\Feature\Guardian;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\User;

/**
 * Linking a Guardian record to an existing application account (M16 Parent
 * Portal foundation, `docs/parent-portal.md`) — mirrors M11's
 * `LinkTeacherUserRequest` exactly. `guardian.manage` only; never creates an
 * account or sends an invitation.
 */
class GuardianUserLinkTest extends GuardianTestCase
{
    public function test_a_manager_can_link_a_guardian_to_an_existing_member(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Parent);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/guardians/{$guardian->id}")
            ->patch("/guardians/{$guardian->id}/user", ['user_id' => $member->id])
            ->assertRedirect("/guardians/{$guardian->id}");

        $this->assertSame($member->id, $guardian->fresh()->user_id);
    }

    public function test_a_manager_can_unlink_a_guardian(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Parent);
        $guardian->user_id = $member->id;
        $guardian->save();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/guardians/{$guardian->id}/user", ['user_id' => ''])->assertRedirect();

        $this->assertNull($guardian->fresh()->user_id);
    }

    public function test_the_account_must_be_a_member_of_the_active_school(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $this->app->forgetScopedInstances();

        $stranger = $this->stranger();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/guardians/{$guardian->id}")
            ->patch("/guardians/{$guardian->id}/user", ['user_id' => $stranger->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($guardian->fresh()->user_id);
    }

    public function test_an_account_cannot_be_linked_to_two_guardians_in_the_same_school(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $first = Guardian::factory()->create();
        $second = Guardian::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Parent);
        $first->user_id = $member->id;
        $first->save();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from("/guardians/{$second->id}")
            ->patch("/guardians/{$second->id}/user", ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($second->fresh()->user_id);
    }

    public function test_the_same_account_can_be_linked_to_a_guardian_in_a_different_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enterSchool($schoolA);
        $guardianA = Guardian::factory()->create();
        $this->app->forgetScopedInstances();
        $this->enterSchool($schoolB);
        $guardianB = Guardian::factory()->create();
        $this->app->forgetScopedInstances();

        $member = User::factory()->create();
        $member->joinSchool($schoolA, Role::Parent);
        $member->joinSchool($schoolB, Role::Parent);

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->patch("/guardians/{$guardianA->id}/user", ['user_id' => $member->id]);
        $this->assertSame($member->id, $guardianA->fresh()->user_id);

        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);
        $this->patch("/guardians/{$guardianB->id}/user", ['user_id' => $member->id]);
        $this->assertSame($member->id, $guardianB->fresh()->user_id);
    }

    public function test_view_only_roles_cannot_link_a_guardian(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $member = User::factory()->create();
        $member->joinSchool($school, Role::Parent);
        $this->app->forgetScopedInstances();

        foreach ($this->guardianRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->patch("/guardians/{$guardian->id}/user", ['user_id' => $member->id])->assertForbidden();
            $this->flushSession();
        }

        $this->assertNull($guardian->fresh()->user_id);
    }

    public function test_linking_is_tenant_isolated(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enterSchool($schoolB);
        $guardianB = Guardian::factory()->create();
        $this->app->forgetScopedInstances();

        // A genuine member of School A — passes the payload's own validation
        // — proves the {guardian} route param itself is what 404s here, not
        // an incidental "user_id doesn't belong to this school" rejection.
        $this->enterSchool($schoolA);
        $memberOfA = User::factory()->create();
        $memberOfA->joinSchool($schoolA, Role::Parent);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->patch("/guardians/{$guardianB->id}/user", ['user_id' => $memberOfA->id])->assertNotFound();
    }
}
