<?php

namespace Tests\Feature\Members;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * `school_user` is not a BelongsToSchool model, so the Members module enforces
 * tenant isolation itself. School A's admin must not be able to see or touch
 * School B's memberships.
 */
class MemberCrossSchoolTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_cannot_change_the_role_of_a_member_of_another_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $adminA = $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $teacherB = $this->memberOf($schoolB, Role::Teacher);

        $this->patch("/members/{$teacherB->id}", ['role' => Role::Principal->value])
            ->assertNotFound();

        $this->assertSame(Role::Teacher, $this->storedRole($teacherB, $schoolB));
    }

    public function test_admin_cannot_remove_a_member_of_another_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $teacherB = $this->memberOf($schoolB, Role::Teacher);

        $this->delete("/members/{$teacherB->id}")->assertNotFound();

        $this->assertDatabaseHas('school_user', [
            'user_id' => $teacherB->id,
            'school_id' => $schoolB->id,
        ]);
    }

    public function test_a_dual_school_user_only_shows_up_in_the_school_they_are_queried_for(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $adminA = $this->actingAsMemberOf($schoolA, Role::SchoolAdmin, ['name' => 'Admin A']);

        $dual = $this->memberOf($schoolA, Role::Teacher, ['name' => 'Dual Person']);
        $dual->joinSchool($schoolB, Role::SchoolAdmin);

        // In A's members list, Dual Person appears as a Teacher, not a School Admin.
        $this->get('/members')
            ->assertOk()
            ->assertSee('Dual Person')
            ->assertSee(Role::Teacher->label());
    }

    public function test_role_changes_are_scoped_to_the_acting_school_only(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);

        $dual = $this->memberOf($schoolA, Role::Teacher);
        $dual->joinSchool($schoolB, Role::Bursar);

        $this->patch("/members/{$dual->id}", ['role' => Role::Principal->value])->assertRedirect();

        $this->assertSame(Role::Principal, $this->storedRole($dual, $schoolA), 'changed in A');
        $this->assertSame(Role::Bursar, $this->storedRole($dual, $schoolB), 'untouched in B');
    }
}
