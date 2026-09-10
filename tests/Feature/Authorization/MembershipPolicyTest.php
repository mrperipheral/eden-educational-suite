<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\SchoolUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class MembershipPolicyTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private function membership(User $user, int $schoolId): SchoolUser
    {
        return SchoolUser::query()->where('user_id', $user->id)->where('school_id', $schoolId)->firstOrFail();
    }

    public function test_view_any_requires_the_member_view_permission(): void
    {
        $school = $this->enterSchool($this->newSchool());

        $admin = $this->memberOf($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->assertTrue($admin->can('viewAny', SchoolUser::class));
        $this->assertFalse($teacher->can('viewAny', SchoolUser::class));
    }

    public function test_assign_role_requires_permission_forbids_self_and_blocks_escalation(): void
    {
        $school = $this->enterSchool($this->newSchool());

        $admin = $this->memberOf($school, Role::SchoolAdmin);
        $principal = $this->memberOf($school, Role::Principal);
        $teacher = $this->memberOf($school, Role::Teacher);

        $teacherMembership = $this->membership($teacher, $school->id);
        $principalMembership = $this->membership($principal, $school->id);
        $adminMembership = $this->membership($admin, $school->id);

        // Admin can promote a teacher to principal.
        $this->assertTrue($admin->can('assignRole', [$teacherMembership, Role::Principal]));

        // Principal cannot grant school_admin (escalation) …
        $this->assertFalse($principal->can('assignRole', [$teacherMembership, Role::SchoolAdmin]));
        // … but can grant teacher/staff/parent (tier ≤ their own).
        $this->assertTrue($principal->can('assignRole', [$teacherMembership, Role::Staff]));

        // Teacher has no member.assign-role permission at all.
        $this->assertFalse($teacher->can('assignRole', [$principalMembership, Role::Teacher]));

        // Nobody can change their own role.
        $this->assertFalse($admin->can('assignRole', [$adminMembership, Role::Principal]));
    }

    public function test_remove_requires_permission_and_forbids_self(): void
    {
        $school = $this->enterSchool($this->newSchool());

        $admin = $this->memberOf($school, Role::SchoolAdmin);
        $principal = $this->memberOf($school, Role::Principal);
        $teacher = $this->memberOf($school, Role::Teacher);

        $teacherMembership = $this->membership($teacher, $school->id);
        $adminMembership = $this->membership($admin, $school->id);

        $this->assertTrue($admin->can('remove', $teacherMembership));
        // Principal lacks member.remove.
        $this->assertFalse($principal->can('remove', $teacherMembership));
        // Not yourself.
        $this->assertFalse($admin->can('remove', $adminMembership));
    }
}
