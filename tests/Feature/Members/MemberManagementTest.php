<?php

namespace Tests\Feature\Members;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_index_lists_only_the_current_schools_members(): void
    {
        $school = $this->newSchool();
        $other = $this->newSchool();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin, ['name' => 'Ada Admin']);
        $this->memberOf($school, Role::Teacher, ['name' => 'Tunde Teacher']);
        $this->memberOf($other, Role::Teacher, ['name' => 'Other Schooler']);

        $this->get('/members')
            ->assertOk()
            ->assertSee('Ada Admin')
            ->assertSee('Tunde Teacher')
            ->assertDontSee('Other Schooler');
    }

    public function test_index_requires_the_member_view_permission(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Bursar, Role::Staff, Role::Parent, Role::Student, null] as $role) {
            $user = $this->actingAsMemberOf($school, $role);
            $this->get('/members')->assertForbidden();
        }

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/members')->assertOk();
    }

    public function test_role_filter_narrows_the_list(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->memberOf($school, Role::Teacher, ['name' => 'Filtered Teacher']);
        $this->memberOf($school, Role::Bursar, ['name' => 'Hidden Bursar']);

        $this->get('/members?role=teacher')
            ->assertOk()
            ->assertSee('Filtered Teacher')
            ->assertDontSee('Hidden Bursar');

        $this->get('/members?role=bursar')
            ->assertOk()
            ->assertSee('Hidden Bursar')
            ->assertDontSee('Filtered Teacher');
    }

    public function test_school_admin_can_change_a_members_role(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->patch("/members/{$teacher->id}", ['role' => Role::Principal->value])
            ->assertRedirect();

        $this->assertSame(Role::Principal, $this->storedRole($teacher, $school));
    }

    public function test_principal_cannot_escalate_a_member_to_school_admin(): void
    {
        $school = $this->newSchool();
        $principal = $this->actingAsMemberOf($school, Role::Principal);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->from('/members')
            ->patch("/members/{$teacher->id}", ['role' => Role::SchoolAdmin->value])
            ->assertForbidden();

        $this->assertSame(Role::Teacher, $this->storedRole($teacher, $school));
    }

    public function test_a_user_cannot_change_their_own_role(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/members/{$admin->id}", ['role' => Role::Teacher->value])
            ->assertForbidden();
    }

    public function test_invalid_role_is_rejected(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->from('/members')
            ->patch("/members/{$teacher->id}", ['role' => 'emperor'])
            ->assertSessionHasErrors('role');
    }

    public function test_school_admin_can_remove_a_member(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->delete("/members/{$teacher->id}")->assertRedirect();

        $this->assertDatabaseMissing('school_user', [
            'user_id' => $teacher->id,
            'school_id' => $school->id,
        ]);
        $this->assertDatabaseHas('users', ['id' => $teacher->id]); // the account survives
    }

    public function test_a_user_cannot_remove_themselves(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->delete("/members/{$admin->id}")->assertForbidden();

        $this->assertDatabaseHas('school_user', ['user_id' => $admin->id, 'school_id' => $school->id]);
    }

    public function test_principal_cannot_remove_a_member(): void
    {
        $school = $this->newSchool();
        $principal = $this->actingAsMemberOf($school, Role::Principal);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->delete("/members/{$teacher->id}")->assertForbidden();
        $this->assertDatabaseHas('school_user', ['user_id' => $teacher->id, 'school_id' => $school->id]);
    }

    public function test_platform_admin_can_manage_members_of_a_school_they_entered(): void
    {
        $school = $this->newSchool();
        $teacher = $this->memberOf($school, Role::Teacher);

        $admin = User::factory()->platformAdmin()->create();
        $this->actingAs($admin)->withSession([EnforceTenant::SESSION_KEY => $school->id]);

        $this->get('/members')->assertOk()->assertSee($teacher->name);
        $this->patch("/members/{$teacher->id}", ['role' => Role::Bursar->value])->assertRedirect();

        $this->assertSame(Role::Bursar, $this->storedRole($teacher, $school));
    }

    public function test_guests_and_non_members_cannot_reach_member_routes(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);

        $this->get('/members')->assertRedirect('/login');

        // authenticated but no school context → pushed to the picker
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get('/members')->assertRedirect(route('school-context.create'));
    }
}
