<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\User;

/**
 * Module gating, permission gating, and the safe empty states a Parent
 * Portal visitor sees before any real family data is at stake (see
 * `docs/parent-portal.md`).
 */
class ParentAuthorizationTest extends ParentPortalTestCase
{
    public function test_the_portal_is_unavailable_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school);
        $this->disableParentPortal($school);
        $this->actingAsParent($school, $user);

        $this->get('/parent')->assertNotFound();
    }

    public function test_roles_without_the_parent_permission_are_forbidden(): void
    {
        $school = $this->newSchool();

        foreach ($this->parentPortalRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/parent')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_unauthenticated_visitors_are_redirected_to_login(): void
    {
        $this->get('/parent')->assertRedirect(route('login'));
    }

    public function test_a_parent_with_no_linked_guardian_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school, Role::Parent);
        $this->actingAsParent($school, $user);

        $this->get('/parent')
            ->assertOk()
            ->assertSee(__('No students are currently linked to your account'));
    }

    public function test_a_guardian_with_no_linked_students_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 0);
        $this->actingAsParent($school, $user);

        $this->get('/parent')
            ->assertOk()
            ->assertSee(__('No students are currently linked to your account'));
    }

    public function test_a_linked_parent_sees_their_children(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 2);
        $this->actingAsParent($school, $user);

        $response = $this->get('/parent')->assertOk();
        $response->assertSee($students[0]->fullName());
        $response->assertSee($students[1]->fullName());
    }

    public function test_school_admin_can_open_the_portal_but_sees_the_empty_state_without_a_linked_guardian(): void
    {
        // School Admin holds every permission (including portal.parent), but
        // is never itself a Guardian — the authorizer, not the Gate, is what
        // actually protects family data.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/parent')
            ->assertOk()
            ->assertSee(__('No students are currently linked to your account'));
    }

    public function test_module_on_without_permission_is_still_forbidden(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Bursar);

        $this->get('/parent')->assertForbidden();
    }

    public function test_a_guardian_record_alone_without_a_parent_role_still_grants_no_access(): void
    {
        // Being linked as a Guardian is necessary but not sufficient — the
        // account must also hold the portal.parent permission (i.e. the
        // Parent role) in this school.
        $school = $this->newSchool();
        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Teacher);
        $guardian = Guardian::factory()->create();
        $guardian->user_id = $user->id;
        $guardian->save();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get('/parent')->assertForbidden();
    }
}
