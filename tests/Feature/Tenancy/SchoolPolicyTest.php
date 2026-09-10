<?php

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolPolicyTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_platform_level_actions_require_a_platform_admin(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school);
        $admin = User::factory()->platformAdmin()->create();

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse($member->can($ability, School::class));
            $this->assertTrue($admin->can($ability, School::class));
        }

        foreach (['update', 'delete'] as $ability) {
            $this->assertFalse($member->can($ability, $school));
            $this->assertTrue($admin->can($ability, $school));
        }
    }

    public function test_view_is_allowed_for_members_and_platform_admins_only(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school);
        $stranger = User::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->assertTrue($member->can('view', $school));
        $this->assertTrue($admin->can('view', $school));
        $this->assertFalse($stranger->can('view', $school));
    }

    public function test_enter_requires_an_active_school_the_user_can_access(): void
    {
        $active = $this->newSchool();
        $suspended = School::factory()->suspended()->create();

        $member = $this->memberOf($active);
        $suspendedMember = $this->memberOf($suspended);
        $admin = User::factory()->platformAdmin()->create();

        $this->assertTrue($member->can('enter', $active));
        $this->assertFalse($suspendedMember->can('enter', $suspended), 'suspended school cannot be entered');
        $this->assertFalse($admin->can('enter', $suspended));
        $this->assertTrue($admin->can('enter', $active));
    }
}
