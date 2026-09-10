<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolAndMembershipTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_school_defaults_to_active_and_reports_status(): void
    {
        $school = School::factory()->create();

        $this->assertSame(SchoolStatus::Active, $school->status);
        $this->assertTrue($school->isActive());

        $suspended = School::factory()->suspended()->create();
        $this->assertFalse($suspended->isActive());
    }

    public function test_active_scope_filters_suspended_schools(): void
    {
        School::factory()->count(2)->create();
        School::factory()->suspended()->create();

        $this->assertSame(2, School::query()->active()->count());
    }

    public function test_school_uses_slug_as_its_route_key(): void
    {
        $this->assertSame('slug', (new School)->getRouteKeyName());
    }

    public function test_school_status_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        // guarded attribute + strict models
        (new School)->fill(['name' => 'x', 'slug' => 'x', 'status' => 'suspended']);
    }

    public function test_user_platform_admin_flag_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new User)->fill(['name' => 'x', 'email' => 'x@example.com', 'password' => 'x', 'is_platform_admin' => true]);
    }

    public function test_membership_relationship_is_bidirectional(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school);

        $this->assertTrue($user->schools->contains($school));
        $this->assertTrue($school->users->contains($user));
    }

    public function test_belongs_to_school_checks_membership(): void
    {
        $mine = $this->newSchool();
        $notMine = $this->newSchool();
        $user = $this->memberOf($mine);

        $this->assertTrue($user->belongsToSchool($mine));
        $this->assertTrue($user->belongsToSchool($mine->id));
        $this->assertFalse($user->belongsToSchool($notMine));
    }

    public function test_can_access_school_for_member_non_member_and_platform_admin(): void
    {
        $school = $this->newSchool();

        $member = $this->memberOf($school);
        $stranger = User::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->assertTrue($member->canAccessSchool($school));
        $this->assertFalse($stranger->canAccessSchool($school));
        $this->assertTrue($admin->canAccessSchool($school), 'platform admin can access any school');
    }

    public function test_deleting_a_school_cascades_membership_rows(): void
    {
        $school = $this->newSchool();
        $user = $this->memberOf($school);

        $school->delete();

        $this->assertDatabaseMissing('school_user', ['user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]); // the user survives
    }
}
