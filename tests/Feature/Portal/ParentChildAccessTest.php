<?php

namespace Tests\Feature\Portal;

use App\Enums\Role;
use App\Models\Student;

/**
 * The core ownership guarantee: a parent may only ever reach a student they
 * are legitimately linked to, in the school they are currently in — never by
 * guessing, tampering with, or reusing another id (see `docs/parent-portal.md`
 * §"Tenant isolation" / §"Child switcher").
 */
class ParentChildAccessTest extends ParentPortalTestCase
{
    public function test_a_parent_can_open_their_own_childs_profile(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $this->actingAsParent($school, $user);

        $this->get("/parent/children/{$students[0]->id}")
            ->assertOk()
            ->assertSee($students[0]->fullName());
    }

    public function test_a_parent_cannot_open_an_unrelated_student_in_the_same_school(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        $stranger = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$stranger->id}")->assertNotFound();
    }

    public function test_a_parent_cannot_manipulate_the_student_id_to_reach_another_family(): void
    {
        $school = $this->newSchool();
        [$userA] = $this->parentWithChildren($school, 1);
        [, , $studentsB] = $this->parentWithChildren($school, 1);

        $this->actingAsParent($school, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}")->assertNotFound();
    }

    public function test_a_parent_cannot_access_a_student_from_another_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->parentWithChildren($schoolA, 1);
        [, , $studentsB] = $this->parentWithChildren($schoolB, 1);

        $this->actingAsParent($schoolA, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}")->assertNotFound();
    }

    public function test_a_parent_cannot_change_their_active_school_to_reach_another_familys_child(): void
    {
        // Even if a parent is (somehow) a member of both schools, the active
        // tenant — not a client-supplied school id — governs which Guardian
        // record (and therefore which children) resolves.
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        [$userA] = $this->parentWithChildren($schoolA, 1);
        [, , $studentsB] = $this->parentWithChildren($schoolB, 1);
        $userA->joinSchool($schoolB, Role::Parent);

        // Still in School A's context — School B's child is invisible.
        $this->actingAsParent($schoolA, $userA);
        $this->get("/parent/children/{$studentsB[0]->id}")->assertNotFound();

        // Switching context to School B: userA has no Guardian record there,
        // so they see the empty state, never School B's other family's child.
        $this->actingAsParent($schoolB, $userA);
        $this->get('/parent')->assertOk()->assertSee(__('No students are currently linked to your account'));
        $this->get("/parent/children/{$studentsB[0]->id}")->assertNotFound();
    }

    public function test_a_parent_with_multiple_children_sees_every_authorized_child(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 3);
        $this->actingAsParent($school, $user);

        foreach ($students as $student) {
            $this->get("/parent/children/{$student->id}")->assertOk()->assertSee($student->fullName());
        }
    }

    public function test_switching_child_never_leaks_the_other_childs_page(): void
    {
        // The "switch child" control is expected to list every one of the
        // parent's own authorized children by name (including the one not
        // currently being viewed) — that is not a leak. What must never mix
        // up is the page's own content for the child actually being viewed.
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 2);
        $this->actingAsParent($school, $user);

        $first = $this->get("/parent/children/{$students[0]->id}")->assertOk();
        $first->assertSee($students[0]->admission_number);
        $first->assertDontSee($students[1]->admission_number);

        $second = $this->get("/parent/children/{$students[1]->id}")->assertOk();
        $second->assertSee($students[1]->admission_number);
        $second->assertDontSee($students[0]->admission_number);
    }

    public function test_a_non_numeric_or_nonexistent_student_id_is_a_clean_404(): void
    {
        $school = $this->newSchool();
        [$user] = $this->parentWithChildren($school, 1);
        $this->actingAsParent($school, $user);

        $this->get('/parent/children/999999')->assertNotFound();
    }
}
