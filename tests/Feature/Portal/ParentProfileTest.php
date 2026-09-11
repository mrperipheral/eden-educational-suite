<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;

/**
 * The parent's own guardian profile — deliberately read-only (see
 * `docs/parent-portal.md` §"Account vs guardian profile"). No write route
 * exists for a parent to self-edit their guardian record.
 */
class ParentProfileTest extends ParentPortalTestCase
{
    public function test_a_linked_parent_sees_their_guardian_details(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        [$user, $guardian] = $this->parentWithChildren($school, 1);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get('/parent/profile')->assertOk()->assertSee($guardian->fullName());
    }

    public function test_a_parent_with_no_linked_guardian_sees_a_safe_message(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school, Role::Parent);
        $this->actingAsParent($school, $user);

        $this->get('/parent/profile')
            ->assertOk()
            ->assertSee(__('No guardian record is linked to your account yet. Please contact your school administrator.'));
    }

    public function test_there_is_no_route_for_a_parent_to_edit_their_guardian_record(): void
    {
        $school = $this->newSchool();
        [$user, $guardian] = $this->parentWithChildren($school, 1);
        $this->actingAsParent($school, $user);

        // Only the school-admin-facing PATCH exists, gated `guardian.manage` —
        // a Parent-role user does not hold it.
        $this->patch("/guardians/{$guardian->id}", ['first_name' => 'Hacked'])->assertForbidden();
    }

    public function test_the_account_settings_link_points_at_the_shared_m2_settings_page(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 1);
        $this->actingAsParent($school, $user);

        $this->get('/parent/profile')->assertOk()->assertSee(route('settings.profile.edit'), false);
    }
}
