<?php

namespace Tests\Feature\Guardian;

use App\Enums\Role;
use App\Models\Guardian;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use Illuminate\Support\Facades\Schema;

class GuardianTest extends GuardianTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Guardian::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amaka',
            'last_name' => 'Okonkwo',
            'phone' => '+234 803 111 2222',
            'email' => 'amaka@example.test',
        ], $overrides);
    }

    public function test_admin_can_create_a_guardian(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/guardians', $this->payload(['notes' => 'Collects on Fridays.']))
            ->assertRedirect();

        $guardian = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('Amaka', $guardian->first_name);
        $this->assertSame($school->id, $guardian->school_id);
    }

    public function test_admin_can_edit_a_guardian(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create(['first_name' => 'Old']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/guardians/{$guardian->id}", $this->payload(['first_name' => 'New']))
            ->assertRedirect(route('guardians.show', $guardian->id));

        $this->assertSame('New', $guardian->fresh()->first_name);
    }

    public function test_required_fields_and_formats_are_validated(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/guardians/create')->post('/guardians', [
            'first_name' => '', 'last_name' => '',
            'email' => 'not-an-email', 'phone' => 'abc!!!',
        ])->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_guardian_pii_columns_are_limited_to_contact_data(): void
    {
        $columns = Schema::getColumnListing('guardians');

        foreach (['bvn', 'nin', 'national_id', 'ssn', 'income', 'salary', 'medical', 'blood_group', 'password', 'remember_token'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "guardians should not store `{$forbidden}`");
        }
    }

    public function test_list_search_and_pagination(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Guardian::factory()->create(['first_name' => 'Ngozi', 'last_name' => 'Findme', 'phone' => '08012345678']);
        Guardian::factory()->create(['first_name' => 'Tunde', 'last_name' => 'Other']);
        Guardian::factory()->count(30)->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/guardians?q=findme')->assertOk()->assertSee('Ngozi')->assertDontSee('Tunde');
        $this->get('/guardians?q=08012345678')->assertOk()->assertSee('Ngozi');

        $this->get('/guardians')->assertOk()
            ->assertViewHas('guardians', fn ($p) => $p->perPage() === 25 && $p->total() === 32 && $p->count() === 25);
    }

    // -- Authorization ------------------------------------------------------

    public function test_view_roles_can_list_but_not_create_or_edit(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $guardian = Guardian::factory()->create();
        $this->app->forgetScopedInstances();

        foreach ($this->guardianRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/guardians')->assertOk();
            $this->get("/guardians/{$guardian->id}")->assertOk();
            $this->get('/guardians/create')->assertForbidden();
            $this->from('/guardians')->post('/guardians', $this->payload())->assertForbidden();
            $this->patch("/guardians/{$guardian->id}", $this->payload())->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();

        foreach ($this->guardianRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/guardians')->assertForbidden();
            $this->flushSession();
        }
    }

    // -- Module activation ------------------------------------------------

    public function test_routes_are_unavailable_when_the_guardians_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->disableGuardians($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/guardians')->assertNotFound();
        $this->get('/guardians/create')->assertNotFound();
        $this->post('/guardians', $this->payload())->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permissions(): void
    {
        // Guardians module is on by default; a Parent still cannot see guardians.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Parent);
        $this->get('/guardians')->assertForbidden();
    }

    // -- Tenant isolation -----------------------------------------------

    public function test_guardians_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $guardianA = Guardian::factory()->create(['first_name' => 'Ada', 'last_name' => 'Secret', 'email' => 'secret@a.test']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/guardians')->assertOk()->assertDontSee('secret@a.test');
        $this->get("/guardians/{$guardianA->id}")->assertNotFound();
        $this->get("/guardians/{$guardianA->id}/edit")->assertNotFound();
        $this->patch("/guardians/{$guardianA->id}", $this->payload())->assertNotFound();

        $this->assertSame('Ada', $guardianA->fresh()->first_name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $guardian = Guardian::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $guardian->school_id = $b->id;
        $guardian->save();
    }

    public function test_school_id_in_the_payload_is_ignored(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/guardians', $this->payload(['school_id' => $b->id]))->assertRedirect();

        $guardian = $this->rowsFor($a->id)->firstOrFail();
        $this->assertSame($a->id, $guardian->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }
}
