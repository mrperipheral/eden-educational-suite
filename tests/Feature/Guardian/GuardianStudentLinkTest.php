<?php

namespace Tests\Feature\Guardian;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Support\Facades\DB;

class GuardianStudentLinkTest extends GuardianTestCase
{
    private function linksFor(int $schoolId)
    {
        return GuardianStudent::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    /** A guardian that belongs to the given school. */
    private function guardianFor(School $school, array $attributes = []): Guardian
    {
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create($attributes);
        $this->app->forgetScopedInstances();

        return $guardian;
    }

    /** @return array<string, mixed> */
    private function payload(Student $student, Guardian $guardian, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => 'mother',
        ], $overrides);
    }

    public function test_admin_links_a_guardian_to_a_student_from_the_student_workflow(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/guardians/students/{$student->id}/link")->assertOk()->assertSee($guardian->fullName());

        $this->post('/guardians/links', $this->payload($student, $guardian, ['relationship' => 'father', 'is_primary' => '1']))
            ->assertRedirect(route('students.show', $student->id));

        $link = $this->linksFor($school->id)->firstOrFail();
        $this->assertSame($student->id, $link->student_id);
        $this->assertSame($guardian->id, $link->guardian_id);
        $this->assertSame($school->id, $link->school_id);
        $this->assertSame(GuardianRelationship::Father, $link->relationship);
        $this->assertTrue($link->is_primary);
    }

    public function test_a_student_can_have_multiple_guardians(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $g1 = $this->guardianFor($school);
        $g2 = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/guardians/links', $this->payload($student, $g1, ['relationship' => 'mother', 'is_primary' => '1']));
        $this->post('/guardians/links', $this->payload($student, $g2, ['relationship' => 'father']));

        $this->assertSame(2, $this->linksFor($school->id)->where('student_id', $student->id)->count());
    }

    public function test_a_guardian_can_be_linked_to_multiple_students(): void
    {
        $school = $this->newSchool();
        $s1 = $this->studentFor($school);
        $s2 = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/guardians/links', $this->payload($s1, $guardian, ['relationship' => 'legal_guardian']));
        $this->post('/guardians/links', $this->payload($s2, $guardian, ['relationship' => 'legal_guardian']));

        $this->assertSame(2, $this->linksFor($school->id)->where('guardian_id', $guardian->id)->count());
    }

    public function test_duplicate_student_guardian_links_are_rejected(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/guardians/links', $this->payload($student, $guardian));
        $this->from(route('students.show', $student->id))
            ->post('/guardians/links', $this->payload($student, $guardian, ['relationship' => 'father']))
            ->assertSessionHasErrors('guardian_id');

        $this->assertSame(1, $this->linksFor($school->id)->count());
    }

    public function test_relationship_type_is_required_and_validated(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from("/guardians/students/{$student->id}/link")
            ->post('/guardians/links', $this->payload($student, $guardian, ['relationship' => 'cousin-twice-removed']))
            ->assertSessionHasErrors('relationship');

        $this->from("/guardians/students/{$student->id}/link")
            ->post('/guardians/links', $this->payload($student, $guardian, ['relationship' => '']))
            ->assertSessionHasErrors('relationship');

        $this->assertSame(0, $this->linksFor($school->id)->count());
    }

    public function test_at_most_one_primary_guardian_per_student(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $g1 = $this->guardianFor($school);
        $g2 = $this->guardianFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/guardians/links', $this->payload($student, $g1, ['is_primary' => '1']));
        $this->post('/guardians/links', $this->payload($student, $g2, ['relationship' => 'father', 'is_primary' => '1']));

        $primary = $this->linksFor($school->id)->where('student_id', $student->id)->where('is_primary', true)->get();
        $this->assertCount(1, $primary, 'only the most recent primary designation stands');
        $this->assertSame($g2->id, $primary->first()->guardian_id);
    }

    public function test_editing_a_link_can_change_relationship_and_promote_to_primary(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $g1 = $this->guardianFor($school);
        $g2 = $this->guardianFor($school);
        $this->enterSchool($school);
        $link1 = $student->guardianLinks()->create(['guardian_id' => $g1->id, 'relationship' => 'mother', 'is_primary' => true]);
        $link2 = $student->guardianLinks()->create(['guardian_id' => $g2->id, 'relationship' => 'other', 'is_primary' => false]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('students.show', $student->id))
            ->patch("/guardians/links/{$link2->id}", ['relationship' => 'father', 'is_primary' => '1'])
            ->assertRedirect();

        $this->assertSame(GuardianRelationship::Father, $link2->fresh()->relationship);
        $this->assertTrue($link2->fresh()->is_primary);
        $this->assertFalse($link1->fresh()->is_primary, 'the previous primary was demoted');
    }

    public function test_a_link_can_be_removed_without_deleting_the_records(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->enterSchool($school);
        $link = $student->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'mother']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('guardians.show', $guardian->id))
            ->delete("/guardians/links/{$link->id}")
            ->assertRedirect();

        $this->assertSame(0, $this->linksFor($school->id)->count());
        $this->assertNotNull($student->fresh(), 'the student record is kept');
        $this->assertNotNull($guardian->fresh(), 'the guardian record is kept');
    }

    public function test_removing_a_student_cascades_its_guardian_links_only(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->enterSchool($school);
        $student->guardianLinks()->create(['guardian_id' => $guardian->id, 'relationship' => 'mother']);

        // Students are never hard-deleted in the app; this asserts the FK wiring
        // only, so a future data-erasure tool behaves.
        Student::withoutGlobalScopes()->whereKey($student->id)->delete();

        $this->assertSame(0, $this->linksFor($school->id)->count());
        $this->assertNotNull($guardian->fresh());
    }

    // -- Cross-school ---------------------------------------------------

    public function test_cross_school_student_or_guardian_ids_are_rejected_without_leaking(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $studentA = $this->studentFor($a);
        $guardianA = $this->guardianFor($a);
        $studentB = $this->studentFor($b);
        $guardianB = $this->guardianFor($b);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        // School A's student with School B's guardian, and vice versa.
        $this->from(route('students.show', $studentB->id))
            ->post('/guardians/links', ['student_id' => $studentA->id, 'guardian_id' => $guardianB->id, 'relationship' => 'mother'])
            ->assertSessionHasErrors('student_id');

        $this->from(route('students.show', $studentB->id))
            ->post('/guardians/links', ['student_id' => $studentB->id, 'guardian_id' => $guardianA->id, 'relationship' => 'mother'])
            ->assertSessionHasErrors('guardian_id');

        $this->assertSame(0, $this->linksFor($a->id)->count());
        $this->assertSame(0, $this->linksFor($b->id)->count());
    }

    public function test_the_link_form_only_lists_the_active_schools_guardians(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        Guardian::factory()->create(['first_name' => 'Aisha', 'last_name' => 'FromSchoolA', 'email' => 'a-only@a.test']);
        $this->app->forgetScopedInstances();

        $studentB = $this->studentFor($b);
        $this->guardianFor($b, ['first_name' => 'Bello', 'last_name' => 'FromSchoolB']);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->get("/guardians/students/{$studentB->id}/link")
            ->assertOk()
            ->assertSee('FromSchoolB')
            ->assertDontSee('FromSchoolA')
            ->assertDontSee('a-only@a.test');
    }

    public function test_link_routes_are_tenant_safe_for_cross_school_ids(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $studentA = $this->studentFor($a);
        $guardianA = $this->guardianFor($a);
        $this->enterSchool($a);
        $linkA = $studentA->guardianLinks()->create(['guardian_id' => $guardianA->id, 'relationship' => 'mother']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get("/guardians/students/{$studentA->id}/link")->assertNotFound();
        $this->patch("/guardians/links/{$linkA->id}", ['relationship' => 'father'])->assertNotFound();
        $this->delete("/guardians/links/{$linkA->id}")->assertNotFound();

        $this->assertSame(GuardianRelationship::Mother, $linkA->fresh()->relationship);
    }

    public function test_link_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $studentA = $this->studentFor($a);
        $guardianA = $this->guardianFor($a);
        $this->enterSchool($a);
        $link = $studentA->guardianLinks()->create(['guardian_id' => $guardianA->id, 'relationship' => 'mother']);

        $this->expectException(TenantMismatchException::class);
        $link->school_id = $b->id;
        $link->save();
    }

    // -- Authorization + module ---------------------------------------

    public function test_view_roles_cannot_manage_links(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);

        foreach ($this->guardianRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/guardians/students/{$student->id}/link")->assertForbidden();
            $this->from(route('students.show', $student->id))
                ->post('/guardians/links', $this->payload($student, $guardian))->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(0, $this->linksFor($school->id)->count());
    }

    public function test_module_gate_blocks_link_routes(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $guardian = $this->guardianFor($school);
        $this->disableGuardians($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get("/guardians/students/{$student->id}/link")->assertNotFound();
        $this->post('/guardians/links', $this->payload($student, $guardian))->assertNotFound();
    }

    // -- Performance --------------------------------------------------

    public function test_student_profile_does_not_n_plus_one_on_guardians(): void
    {
        $school = $this->newSchool();
        $student = $this->studentFor($school);
        $this->enterSchool($school);
        Guardian::factory()->count(6)->create()->each(fn (Guardian $g) => $student->guardianLinks()->create([
            'guardian_id' => $g->id, 'relationship' => 'other',
        ]));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/students/{$student->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "student profile ran {$queries} queries for 6 guardians");
    }

    public function test_guardian_profile_does_not_n_plus_one_on_students(): void
    {
        $school = $this->newSchool();
        $guardian = $this->guardianFor($school);
        $this->enterSchool($school);
        Student::factory()->count(6)->create()->each(fn (Student $s) => $s->guardianLinks()->create([
            'guardian_id' => $guardian->id, 'relationship' => 'legal_guardian',
        ]));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/guardians/{$guardian->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "guardian profile ran {$queries} queries for 6 students");
    }
}
