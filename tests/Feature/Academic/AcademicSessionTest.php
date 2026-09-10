<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\AcademicSession;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class AcademicSessionTest extends AcademicTestCase
{
    private function rowsFor(int $schoolId)
    {
        return AcademicSession::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_school_admin_can_create_the_first_session_which_becomes_current(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/sessions', [
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-07-31',
        ])->assertRedirect(route('academic.sessions.index'));

        $session = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('2025/2026', $session->name);
        $this->assertTrue((bool) $session->is_current);
    }

    public function test_a_session_can_be_edited_and_history_is_preserved(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $old = AcademicSession::factory()->create(['name' => '2023/2024']);
        $new = AcademicSession::factory()->current()->create(['name' => '2025/2026']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/academic/sessions/{$old->id}", [
            'name' => '2023/2024 (revised)',
            'starts_on' => '2023-09-01',
            'ends_on' => '2024-07-31',
        ])->assertRedirect(route('academic.sessions.show', $old->id));

        $this->assertSame('2023/2024 (revised)', $old->fresh()->name);
        $this->assertSame(2, $this->rowsFor($school->id)->count(), 'the older session is kept');
        $this->assertTrue((bool) $new->fresh()->is_current);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/academic/sessions')->post('/academic/sessions', [
            'name' => 'Bad Range',
            'starts_on' => '2026-01-01',
            'ends_on' => '2025-12-31',
        ])->assertSessionHasErrors('ends_on');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_name_is_unique_per_school_but_reusable_across_schools(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post('/academic/sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
        $this->from('/academic/sessions')->post('/academic/sessions', [
            'name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31',
        ])->assertSessionHasErrors('name');

        $this->flushSession();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->post('/academic/sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rowsFor($a->id)->count());
        $this->assertSame(1, $this->rowsFor($b->id)->count());
    }

    public function test_editing_a_session_ignores_its_own_name_in_the_unique_check(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $session = AcademicSession::factory()->create(['name' => '2025/2026']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/academic/sessions/{$session->id}", [
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-07-31',
        ])->assertSessionHasNoErrors();
    }

    public function test_only_one_session_is_current_per_school(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/academic/sessions', ['name' => '2024/2025', 'starts_on' => '2024-09-01', 'ends_on' => '2025-07-31']);
        $this->post('/academic/sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);

        $first = $this->rowsFor($school->id)->where('name', '2024/2025')->firstOrFail();
        $second = $this->rowsFor($school->id)->where('name', '2025/2026')->firstOrFail();
        $this->assertTrue((bool) $first->is_current);

        $this->put("/academic/sessions/{$second->id}/current")->assertRedirect();

        $this->assertFalse((bool) $first->fresh()->is_current);
        $this->assertTrue((bool) $second->fresh()->is_current);
        $this->assertSame(1, $this->rowsFor($school->id)->where('is_current', true)->count());
    }

    // -- Authorization -------------------------------------------------------

    public function test_manage_roles_can_write_view_roles_cannot(): void
    {
        $school = $this->newSchool();

        foreach ($this->academicRoles()['manage'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/sessions')->assertOk();
            $this->post('/academic/sessions', ['name' => "S-{$role->value}", 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
                ->assertSessionHasNoErrors();
            $this->flushSession();
        }

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/sessions')->assertOk();
            $this->from('/academic/sessions')
                ->post('/academic/sessions', ['name' => "X-{$role->value}", 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
                ->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_unauthorized_roles_get_403(): void
    {
        $school = $this->newSchool();

        foreach ($this->academicRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/academic/sessions')->assertForbidden();
        }
    }

    // -- Module activation --------------------------------------------------

    public function test_the_area_is_unreachable_when_the_academic_module_is_off(): void
    {
        $school = $this->newSchool();
        $this->disableAcademics($school);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/academic/sessions')->assertNotFound();
        $this->post('/academic/sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
            ->assertNotFound();
    }

    // -- Tenant isolation --------------------------------------------------

    public function test_sessions_are_tenant_isolated_and_route_binding_is_safe(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $sessionA = AcademicSession::factory()->create(['name' => 'A-2025']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/academic/sessions')->assertOk()->assertDontSee('A-2025');
        $this->get("/academic/sessions/{$sessionA->id}")->assertNotFound();
        $this->get("/academic/sessions/{$sessionA->id}/edit")->assertNotFound();
        $this->put("/academic/sessions/{$sessionA->id}/current")->assertNotFound();
        $this->patch("/academic/sessions/{$sessionA->id}", ['name' => 'hijack', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
            ->assertNotFound();

        $this->assertSame('A-2025', $sessionA->fresh()->name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $session = AcademicSession::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $session->school_id = $b->id;
        $session->save();
    }
}
