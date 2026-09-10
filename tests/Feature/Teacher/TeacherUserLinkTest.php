<?php

namespace Tests\Feature\Teacher;

use App\Enums\Role;
use App\Models\User;

class TeacherUserLinkTest extends TeacherTestCase
{
    public function test_admin_can_link_a_teacher_to_an_existing_member(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school);
        $member = $this->memberOf($school, Role::Teacher);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}/user", ['user_id' => $member->id])
            ->assertRedirect(route('teachers.show', $teacher->id));

        $this->assertSame($member->id, $teacher->fresh()->user_id);
    }

    public function test_a_teacher_can_be_unlinked_without_deleting_anything(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $teacher->user_id = $member->id;
        $teacher->save();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}/user", ['user_id' => ''])->assertRedirect();

        $this->assertNull($teacher->fresh()->user_id);
        $this->assertNotNull($member->fresh(), 'the account is kept');
        $this->assertNotNull($teacher->fresh(), 'the teacher record is kept');
    }

    public function test_a_user_who_is_not_a_member_of_the_school_cannot_be_linked(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school);
        $stranger = $this->stranger();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.show', $teacher->id))
            ->patch("/teachers/{$teacher->id}/user", ['user_id' => $stranger->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($teacher->fresh()->user_id);
    }

    public function test_a_member_of_another_school_cannot_be_linked(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $teacherA = $this->teacherFor($a);
        $memberB = $this->memberOf($b, Role::Teacher);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->from(route('teachers.show', $teacherA->id))
            ->patch("/teachers/{$teacherA->id}/user", ['user_id' => $memberB->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($teacherA->fresh()->user_id);
    }

    public function test_one_account_cannot_be_linked_to_two_teachers_in_the_same_school(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);
        $t1 = $this->teacherFor($school);
        $t2 = $this->teacherFor($school);
        $this->enterSchool($school);
        $t1->user_id = $member->id;
        $t1->save();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('teachers.show', $t2->id))
            ->patch("/teachers/{$t2->id}/user", ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($t2->fresh()->user_id);
    }

    public function test_re_saving_the_same_link_is_allowed(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $teacher->user_id = $member->id;
        $teacher->save();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}/user", ['user_id' => $member->id])
            ->assertSessionHasNoErrors();
    }

    public function test_the_same_account_can_be_a_teacher_in_two_schools(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $user = User::factory()->create();
        $user->joinSchool($a, Role::Teacher);
        $user->joinSchool($b, Role::Teacher);

        $teacherA = $this->teacherFor($a);
        $teacherB = $this->teacherFor($b);

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->patch("/teachers/{$teacherA->id}/user", ['user_id' => $user->id])->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->patch("/teachers/{$teacherB->id}/user", ['user_id' => $user->id])->assertSessionHasNoErrors();

        $this->assertSame($user->id, $teacherA->fresh()->user_id);
        $this->assertSame($user->id, $teacherB->fresh()->user_id);
    }

    public function test_deleting_the_account_leaves_the_teacher_record_unlinked(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $teacher->user_id = $member->id;
        $teacher->save();

        User::withoutGlobalScopes()->whereKey($member->id)->delete();

        $this->assertNull($teacher->fresh()->user_id);
        $this->assertNotNull($teacher->fresh(), 'the professional record survives account deletion');
    }

    public function test_cross_school_teacher_cannot_be_linked_from_another_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $teacherA = $this->teacherFor($a);
        $memberB = $this->memberOf($b, Role::Teacher);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacherA->id}/user", ['user_id' => $memberB->id])->assertNotFound();

        $this->assertNull($teacherA->fresh()->user_id);
    }

    public function test_view_roles_cannot_link_accounts(): void
    {
        $school = $this->newSchool();
        $member = $this->memberOf($school, Role::Teacher);
        $teacher = $this->teacherFor($school);

        foreach ($this->teacherRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->patch("/teachers/{$teacher->id}/user", ['user_id' => $member->id])->assertForbidden();
            $this->flushSession();
        }

        $this->assertNull($teacher->fresh()->user_id);
    }
}
