<?php

namespace Tests\Feature\Members;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class AddMemberTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_school_admin_can_add_an_existing_user_with_a_role(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $newcomer = User::factory()->create(['email' => 'newcomer@x.example']);

        $this->get('/members/create')->assertOk();

        $this->post('/members', ['email' => 'newcomer@x.example', 'role' => Role::Teacher->value])
            ->assertRedirect(route('members.index'));

        $this->assertSame(Role::Teacher, $this->storedRole($newcomer, $school));
    }

    public function test_email_lookup_is_case_insensitive(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $newcomer = User::factory()->create(['email' => 'mixed@x.example']);

        $this->post('/members', ['email' => 'Mixed@X.Example', 'role' => Role::Staff->value])
            ->assertRedirect();

        $this->assertSame(Role::Staff, $this->storedRole($newcomer, $school));
    }

    public function test_users_without_assign_role_permission_cannot_add_members(): void
    {
        $school = $this->newSchool();
        $target = User::factory()->create(['email' => 'target@x.example']);

        foreach ([Role::Teacher, Role::Bursar, Role::Staff, Role::Parent, null] as $role) {
            $this->actingAsMemberOf($school, $role);

            $this->get('/members/create')->assertForbidden();
            $this->from('/members')->post('/members', ['email' => 'target@x.example', 'role' => Role::Staff->value])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('school_user', ['user_id' => $target->id]);
    }

    public function test_principal_cannot_add_someone_as_school_admin(): void
    {
        $school = $this->newSchool();
        $principal = $this->actingAsMemberOf($school, Role::Principal);
        $target = User::factory()->create(['email' => 'wannabe-admin@x.example']);

        $this->from('/members/create')->post('/members', [
            'email' => 'wannabe-admin@x.example',
            'role' => Role::SchoolAdmin->value,
        ])->assertForbidden();

        $this->assertNull($this->storedRole($target, $school));
    }

    public function test_principal_can_add_a_teacher(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Principal);
        $target = User::factory()->create(['email' => 'new-teacher@x.example']);

        $this->post('/members', ['email' => 'new-teacher@x.example', 'role' => Role::Teacher->value])
            ->assertRedirect();

        $this->assertSame(Role::Teacher, $this->storedRole($target, $school));
    }

    public function test_cannot_add_a_user_who_is_already_a_member(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $existing = $this->memberOf($school, Role::Teacher, ['email' => 'already@x.example']);

        $this->from('/members/create')->post('/members', [
            'email' => 'already@x.example',
            'role' => Role::Staff->value,
        ])->assertSessionHasErrors('email');

        $this->assertSame(Role::Teacher, $this->storedRole($existing, $school), 'role unchanged');
    }

    public function test_cannot_add_an_unknown_or_inactive_account(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        User::factory()->disabled()->create(['email' => 'disabled@x.example']);

        $this->from('/members/create')->post('/members', ['email' => 'ghost@x.example', 'role' => Role::Staff->value])
            ->assertSessionHasErrors('email');

        $this->from('/members/create')->post('/members', ['email' => 'disabled@x.example', 'role' => Role::Staff->value])
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_role_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        User::factory()->create(['email' => 'x@x.example']);

        $this->from('/members/create')->post('/members', ['email' => 'x@x.example', 'role' => 'overlord'])
            ->assertSessionHasErrors('role');
    }

    public function test_adding_a_member_is_scoped_to_the_current_school_only(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $target = User::factory()->create(['email' => 'crossschool@x.example']);

        $this->post('/members', ['email' => 'crossschool@x.example', 'role' => Role::Teacher->value])
            ->assertRedirect();

        $this->assertSame(Role::Teacher, $this->storedRole($target, $schoolA));
        $this->assertNull($this->storedRole($target, $schoolB), 'not added to any other school');
    }

    public function test_adding_a_member_does_not_change_the_acting_admins_context(): void
    {
        $schoolA = $this->newSchool();
        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        User::factory()->create(['email' => 'ctx@x.example']);

        $this->post('/members', ['email' => 'ctx@x.example', 'role' => Role::Staff->value])->assertRedirect();

        // The school-context session key is untouched — the admin is still in A.
        $this->assertSame($schoolA->id, session(EnforceTenant::SESSION_KEY));
    }

    public function test_platform_admin_in_context_can_add_members(): void
    {
        $school = $this->newSchool();
        $admin = $this->actingAsPlatformAdmin($school);
        $target = User::factory()->create(['email' => 'padd@x.example']);

        $this->post('/members', ['email' => 'padd@x.example', 'role' => Role::Bursar->value])
            ->assertRedirect();

        $this->assertSame(Role::Bursar, $this->storedRole($target, $school));
    }
}
