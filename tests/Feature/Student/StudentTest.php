<?php

namespace Tests\Feature\Student;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class StudentTest extends StudentTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Student::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Chidi',
            'last_name' => 'Okafor',
            'admission_number' => 'STU-0001',
            'date_of_birth' => '2015-04-02',
            'gender' => 'male',
        ], $overrides);
    }

    public function test_admin_can_create_a_student(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/students', $this->payload(['admission_number' => 'stu-77', 'notes' => 'Transferred in.']))
            ->assertRedirect();

        $student = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('Chidi', $student->first_name);
        $this->assertSame('stu-77', $student->admission_number);
        $this->assertSame(StudentStatus::Active, $student->status, 'new students default to active');
    }

    public function test_admin_can_edit_a_student(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create(['first_name' => 'Old', 'admission_number' => 'A-1']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/students/{$student->id}", $this->payload([
            'first_name' => 'New', 'last_name' => 'Name', 'admission_number' => 'A-1',
        ]))->assertRedirect(route('students.show', $student->id));

        $this->assertSame('New', $student->fresh()->first_name);
    }

    public function test_required_fields_and_formats_are_validated(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/students/create')->post('/students', [
            'first_name' => '', 'last_name' => '', 'admission_number' => 'bad code!',
            'date_of_birth' => now()->addYear()->toDateString(),
            'gender' => 'unknown', 'contact_email' => 'nope',
        ])->assertSessionHasErrors(['first_name', 'last_name', 'admission_number', 'date_of_birth', 'gender', 'contact_email']);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_admission_number_is_unique_within_a_school(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/students', $this->payload(['admission_number' => 'DUP-1']));
        $this->from('/students/create')->post('/students', $this->payload([
            'first_name' => 'Other', 'admission_number' => 'DUP-1',
        ]))->assertSessionHasErrors('admission_number');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_the_same_admission_number_is_fine_in_another_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post('/students', $this->payload(['admission_number' => 'SHARED-1']))->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post('/students', $this->payload(['admission_number' => 'SHARED-1']))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rowsFor($a->id)->count());
        $this->assertSame(1, $this->rowsFor($b->id)->count());
    }

    public function test_editing_ignores_the_students_own_admission_number(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create(['admission_number' => 'KEEP-1']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/students/{$student->id}", $this->payload(['admission_number' => 'KEEP-1']))
            ->assertSessionHasNoErrors();
    }

    public function test_status_changes_through_the_dedicated_endpoint_and_records_are_kept(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/students/{$student->id}/status", ['status' => 'withdrawn'])
            ->assertRedirect(route('students.show', $student->id));

        $this->assertSame(StudentStatus::Withdrawn, $student->fresh()->status);
        $this->assertSame(1, $this->rowsFor($school->id)->count(), 'the record is retained, not deleted');

        $this->from(route('students.show', $student->id))
            ->patch("/students/{$student->id}/status", ['status' => 'nonsense'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_is_not_mass_assignable_via_the_edit_form(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/students/{$student->id}", $this->payload(['status' => 'graduated']))->assertRedirect();

        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
    }

    // -- Listing --------------------------------------------------------------

    public function test_list_search_and_status_filter(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Student::factory()->create(['first_name' => 'Ngozi', 'last_name' => 'Eze', 'admission_number' => 'FIND-9']);
        Student::factory()->create(['first_name' => 'Tunde', 'last_name' => 'Bello', 'admission_number' => 'OTHER-3']);
        Student::factory()->status(StudentStatus::Graduated)->create(['first_name' => 'Alum', 'last_name' => 'Ni', 'admission_number' => 'GRAD-1']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/students?q=ngozi')->assertOk()->assertSee('Ngozi')->assertDontSee('Tunde');
        $this->get('/students?q=FIND-9')->assertOk()->assertSee('Ngozi')->assertDontSee('Tunde');
        $this->get('/students?status=graduated')->assertOk()->assertSee('Alum')->assertDontSee('Ngozi');
    }

    public function test_list_is_paginated(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Student::factory()->count(30)->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $response = $this->get('/students')->assertOk();
        $response->assertViewHas('students', fn ($p) => $p->perPage() === 25 && $p->total() === 30 && $p->count() === 25);
    }

    // -- Authorization ------------------------------------------------------

    public function test_view_roles_can_list_but_not_create_or_edit(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        foreach ($this->studentRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/students')->assertOk();
            $this->get("/students/{$student->id}")->assertOk();
            $this->get('/students/create')->assertForbidden();
            $this->from('/students')->post('/students', $this->payload(['admission_number' => "V-{$role->value}"]))->assertForbidden();
            $this->patch("/students/{$student->id}/status", ['status' => 'inactive'])->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();

        foreach ($this->studentRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/students')->assertForbidden();
            $this->flushSession();
        }
    }

    // -- Module activation --------------------------------------------------

    public function test_routes_are_unavailable_when_the_students_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->disableStudents($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/students')->assertNotFound();
        $this->get('/students/create')->assertNotFound();
        $this->post('/students', $this->payload())->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permissions(): void
    {
        // Students module is on by default; a Parent still cannot see students.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Parent);
        $this->get('/students')->assertForbidden();
    }

    // -- Tenant isolation -------------------------------------------------

    public function test_students_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $studentA = Student::factory()->create(['first_name' => 'Ada', 'last_name' => 'Secret', 'admission_number' => 'A-SECRET']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/students')->assertOk()->assertDontSee('A-SECRET');
        $this->get("/students/{$studentA->id}")->assertNotFound();
        $this->get("/students/{$studentA->id}/edit")->assertNotFound();
        $this->patch("/students/{$studentA->id}", $this->payload())->assertNotFound();
        $this->patch("/students/{$studentA->id}/status", ['status' => 'withdrawn'])->assertNotFound();

        $this->assertSame('Ada', $studentA->fresh()->first_name);
        $this->assertSame(StudentStatus::Active, $studentA->fresh()->status);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $student = Student::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $student->school_id = $b->id;
        $student->save();
    }
}
