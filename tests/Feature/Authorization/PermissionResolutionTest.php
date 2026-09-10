<?php

namespace Tests\Feature\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * The heart of the milestone: a permission only ever applies inside the school
 * the user is currently working in.
 */
class PermissionResolutionTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_no_permissions_without_an_active_school(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school, Role::SchoolAdmin);

        // No TenantContext set.
        $this->assertNull($user->roleIn());
        $this->assertFalse($user->hasPermission(Permission::MemberView));
        $this->assertSame([], $user->permissionsIn());
    }

    public function test_permissions_follow_the_role_in_the_active_school(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->assertSame(Role::Teacher, $teacher->roleIn());
        $this->assertTrue($teacher->hasPermission(Permission::AttendanceRecord));
        $this->assertFalse($teacher->hasPermission(Permission::ResultPublish));
        $this->assertFalse($teacher->hasPermission(Permission::MemberView));
    }

    public function test_a_member_without_a_role_has_no_permissions(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $user = $this->memberOf($school, null);

        $this->assertNull($user->roleIn());
        $this->assertFalse($user->hasPermission(Permission::StudentView));
    }

    public function test_role_is_resolved_per_school_for_a_multi_school_user(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $user = User::factory()->create();
        $user->joinSchool($schoolA, Role::Teacher);
        $user->joinSchool($schoolB, Role::Parent);

        $tenant = app(TenantContext::class);

        $tenant->set($schoolA);
        $this->assertSame(Role::Teacher, $user->roleIn());
        $this->assertTrue($user->hasPermission(Permission::AttendanceRecord));
        $this->assertFalse($user->hasPermission(Permission::PortalParent));

        $tenant->set($schoolB);
        // fresh instance to bypass the per-request role memo
        $user = $user->fresh();
        $this->assertSame(Role::Parent, $user->roleIn());
        $this->assertTrue($user->hasPermission(Permission::PortalParent));
        $this->assertFalse($user->hasPermission(Permission::AttendanceRecord));
    }

    public function test_explicit_school_argument_overrides_the_ambient_context(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $user = User::factory()->create();
        $user->joinSchool($schoolA, Role::Bursar);
        $user->joinSchool($schoolB, Role::Student);

        $this->enterSchool($schoolA);

        $this->assertTrue($user->hasPermission(Permission::FinanceManage, $schoolA));
        $this->assertFalse($user->hasPermission(Permission::FinanceManage, $schoolB));
        $this->assertTrue($user->hasPermission(Permission::PortalStudent, $schoolB));
    }

    public function test_platform_admin_holds_all_permissions_within_an_entered_school(): void
    {
        $school = $this->newSchool();
        $admin = User::factory()->platformAdmin()->create(); // not a member

        // No context yet → nothing.
        $this->assertFalse($admin->hasPermission(Permission::FinanceManage));
        $this->assertSame([], $admin->permissionsIn());

        $this->enterSchool($school);
        $this->assertTrue($admin->hasPermission(Permission::FinanceManage));
        $this->assertTrue($admin->hasPermission(Permission::MemberRemove));
        $this->assertEqualsCanonicalizing(Permission::cases(), $admin->permissionsIn());
    }

    public function test_role_memo_is_refreshed_after_assignment_helpers(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $user = $this->memberOf($school, Role::Staff);

        $this->assertSame(Role::Staff, $user->roleIn());

        $user->assignRoleInSchool($school, Role::Teacher);
        $this->assertSame(Role::Teacher, $user->roleIn(), 'memo cleared by assignRoleInSchool');

        $user->leaveSchool($school);
        $this->assertNull($user->roleIn());
    }
}
