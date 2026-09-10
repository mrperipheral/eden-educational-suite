<?php

namespace Tests\Unit\Enums;

use App\Enums\Permission;
use App\Enums\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_every_role_has_a_label(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }

    public function test_school_admin_holds_every_permission(): void
    {
        $this->assertEqualsCanonicalizing(Permission::cases(), Role::SchoolAdmin->permissions());
    }

    public function test_portal_roles_are_minimal(): void
    {
        $this->assertSame([Permission::PortalParent], Role::Parent->permissions());
        $this->assertSame([Permission::PortalStudent], Role::Student->permissions());
    }

    public function test_no_role_grants_a_permission_outside_the_permission_enum(): void
    {
        foreach (Role::cases() as $role) {
            foreach ($role->permissions() as $permission) {
                $this->assertInstanceOf(Permission::class, $permission);
            }
        }
    }

    public function test_grants_reflects_the_bundle(): void
    {
        $this->assertTrue(Role::Teacher->grants(Permission::AttendanceRecord));
        $this->assertFalse(Role::Teacher->grants(Permission::ResultPublish));
        $this->assertFalse(Role::Teacher->grants(Permission::FinanceManage));
    }

    public function test_tiers_are_ordered_admin_highest_portal_lowest(): void
    {
        $this->assertGreaterThan(Role::Principal->tier(), Role::SchoolAdmin->tier());
        $this->assertGreaterThan(Role::Teacher->tier(), Role::Principal->tier());
        $this->assertGreaterThan(Role::Staff->tier(), Role::Teacher->tier());
        $this->assertGreaterThan(Role::Parent->tier(), Role::Staff->tier());
        $this->assertSame(Role::Parent->tier(), Role::Student->tier());
    }

    public function test_only_admin_and_principal_may_assign_roles(): void
    {
        foreach (Role::cases() as $role) {
            $expected = in_array($role, [Role::SchoolAdmin, Role::Principal], true);
            $this->assertSame($expected, $role->grants(Permission::MemberAssignRole), $role->value);
        }
    }
}
