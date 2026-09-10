<?php

namespace Tests\Feature\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class GateIntegrationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_every_permission_is_registered_as_a_gate_ability(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(Gate::has($permission->value), $permission->value);
        }
    }

    public function test_user_can_delegates_to_has_permission_and_is_tenant_composed(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $bursar = $this->memberOf($school, Role::Bursar);

        $this->actingAs($bursar);

        $this->assertTrue(Gate::allows(Permission::FinanceManage->value));
        $this->assertTrue($bursar->can(Permission::FinanceView->value));
        $this->assertFalse($bursar->can(Permission::ResultPublish->value));
        $this->assertFalse($bursar->can(Permission::MemberAssignRole->value));
    }

    public function test_gate_denies_when_no_school_context(): void
    {
        $school = $this->newSchool();
        $admin = $this->memberOf($school, Role::SchoolAdmin);
        $this->actingAs($admin);

        // no TenantContext set
        $this->assertFalse($admin->can(Permission::MemberView->value));
    }

    public function test_no_gate_before_blanket_allow_exists_for_platform_admins(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin);

        // With no school entered, a platform admin can do nothing school-level.
        $this->assertFalse($admin->can(Permission::MemberView->value));
        $this->assertFalse(Gate::forUser($admin)->allows(Permission::SchoolSettingsUpdate->value));
    }
}
