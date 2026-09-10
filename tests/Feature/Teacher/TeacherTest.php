<?php

namespace Tests\Feature\Teacher;

use App\Enums\Role;
use App\Enums\TeacherStatus;
use App\Models\Teacher;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Support\Facades\Schema;

class TeacherTest extends TeacherTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Teacher::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ngozi',
            'last_name' => 'Umeh',
            'employee_number' => 'EMP-0001',
            'email' => 'ngozi@example.test',
            'phone' => '+234 803 111 2222',
        ], $overrides);
    }

    public function test_admin_can_create_a_teacher(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/teachers', $this->payload(['employee_number' => 'emp-77', 'notes' => 'Maths lead.']))
            ->assertRedirect();

        $teacher = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('Ngozi', $teacher->first_name);
        $this->assertSame('emp-77', $teacher->employee_number);
        $this->assertSame(TeacherStatus::Active, $teacher->status, 'new teachers default to active');
        $this->assertNull($teacher->user_id, 'a teacher is not a login by default');
    }

    public function test_admin_can_edit_a_teacher(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school, ['first_name' => 'Old', 'employee_number' => 'E-1']);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}", $this->payload(['first_name' => 'New', 'employee_number' => 'E-1']))
            ->assertRedirect(route('teachers.show', $teacher->id));

        $this->assertSame('New', $teacher->fresh()->first_name);
    }

    public function test_required_fields_and_formats_are_validated(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/teachers/create')->post('/teachers', [
            'first_name' => '', 'last_name' => '', 'employee_number' => 'bad code!',
            'email' => 'nope', 'phone' => 'abc',
        ])->assertSessionHasErrors(['first_name', 'last_name', 'employee_number', 'email', 'phone']);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_teacher_stores_no_identity_or_financial_columns(): void
    {
        $columns = Schema::getColumnListing('teachers');

        foreach (['nin', 'bvn', 'national_id', 'ssn', 'salary', 'bank_account', 'pension_id', 'medical', 'password', 'remember_token'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "teachers should not store `{$forbidden}`");
        }
    }

    public function test_employee_number_is_unique_within_a_school_but_reusable_across_schools(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post('/teachers', $this->payload(['employee_number' => 'DUP-1']))->assertSessionHasNoErrors();
        $this->from('/teachers/create')
            ->post('/teachers', $this->payload(['first_name' => 'Other', 'employee_number' => 'DUP-1']))
            ->assertSessionHasErrors('employee_number');

        $this->flushSession();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post('/teachers', $this->payload(['employee_number' => 'DUP-1']))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rowsFor($a->id)->count());
        $this->assertSame(1, $this->rowsFor($b->id)->count());
    }

    public function test_editing_ignores_the_teachers_own_employee_number(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school, ['employee_number' => 'KEEP-1']);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}", $this->payload(['employee_number' => 'KEEP-1']))
            ->assertSessionHasNoErrors();
    }

    public function test_list_search_and_status_filter(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Teacher::factory()->create(['first_name' => 'Findme', 'last_name' => 'Ade', 'employee_number' => 'FIND-9']);
        Teacher::factory()->create(['first_name' => 'Other', 'last_name' => 'Bello', 'employee_number' => 'OTHER-3']);
        Teacher::factory()->status(TeacherStatus::Resigned)->create(['first_name' => 'Gone', 'last_name' => 'Away', 'employee_number' => 'GONE-1']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/teachers?q=findme')->assertOk()->assertSee('Findme')->assertDontSee('Other');
        $this->get('/teachers?q=FIND-9')->assertOk()->assertSee('Findme');
        $this->get('/teachers?status=resigned')->assertOk()->assertSee('Gone')->assertDontSee('Findme');
    }

    public function test_list_is_paginated(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Teacher::factory()->count(30)->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/teachers')->assertOk()
            ->assertViewHas('teachers', fn ($p) => $p->perPage() === 25 && $p->total() === 30 && $p->count() === 25);
    }

    public function test_status_changes_through_the_dedicated_endpoint_and_records_are_kept(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}/status", ['status' => 'resigned'])
            ->assertRedirect(route('teachers.show', $teacher->id));

        $this->assertSame(TeacherStatus::Resigned, $teacher->fresh()->status);
        $this->assertSame(1, $this->rowsFor($school->id)->count(), 'the record is retained, not deleted');

        $this->from(route('teachers.show', $teacher->id))
            ->patch("/teachers/{$teacher->id}/status", ['status' => 'nonsense'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_is_not_mass_assignable_via_the_edit_form(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/teachers/{$teacher->id}", $this->payload(['status' => 'resigned']))->assertRedirect();

        $this->assertSame(TeacherStatus::Active, $teacher->fresh()->status);
    }

    // -- Authorization ----------------------------------------------------

    public function test_view_roles_can_list_but_not_create_or_edit(): void
    {
        $school = $this->newSchool();
        $teacher = $this->teacherFor($school);

        foreach ($this->teacherRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/teachers')->assertOk();
            $this->get("/teachers/{$teacher->id}")->assertOk();
            $this->get('/teachers/create')->assertForbidden();
            $this->from('/teachers')->post('/teachers', $this->payload(['employee_number' => "V-{$role->value}"]))->assertForbidden();
            $this->patch("/teachers/{$teacher->id}/status", ['status' => 'inactive'])->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();

        foreach ($this->teacherRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/teachers')->assertForbidden();
            $this->flushSession();
        }
    }

    // -- Module activation ----------------------------------------------

    public function test_routes_are_unavailable_when_the_staff_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->disableStaff($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/teachers')->assertNotFound();
        $this->get('/teachers/create')->assertNotFound();
        $this->post('/teachers', $this->payload())->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permissions(): void
    {
        // Staff module is on by default; a Parent still cannot see teachers.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Parent);
        $this->get('/teachers')->assertForbidden();
    }

    // -- Tenant isolation ---------------------------------------------

    public function test_teachers_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $teacherA = $this->teacherFor($a, ['first_name' => 'Ada', 'last_name' => 'Secret', 'email' => 'secret@a.test', 'employee_number' => 'A-SECRET']);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/teachers')->assertOk()->assertDontSee('A-SECRET');
        $this->get("/teachers/{$teacherA->id}")->assertNotFound();
        $this->get("/teachers/{$teacherA->id}/edit")->assertNotFound();
        $this->patch("/teachers/{$teacherA->id}", $this->payload())->assertNotFound();
        $this->patch("/teachers/{$teacherA->id}/status", ['status' => 'resigned'])->assertNotFound();

        $this->assertSame('Ada', $teacherA->fresh()->first_name);
        $this->assertSame(TeacherStatus::Active, $teacherA->fresh()->status);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $teacher = Teacher::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $teacher->school_id = $b->id;
        $teacher->save();
    }

    public function test_school_id_in_the_payload_is_ignored(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/teachers', $this->payload(['school_id' => $b->id]))->assertRedirect();

        $teacher = $this->rowsFor($a->id)->firstOrFail();
        $this->assertSame($a->id, $teacher->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }
}
