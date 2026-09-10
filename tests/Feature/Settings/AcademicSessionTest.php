<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\AcademicSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class AcademicSessionTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function rowsFor(int $schoolId)
    {
        return AcademicSession::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_school_admin_can_create_the_first_academic_session(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/settings/academic-sessions', [
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-07-31',
        ])->assertRedirect(route('academic-sessions.index'));

        $session = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('2025/2026', $session->name);
        $this->assertTrue((bool) $session->is_current, 'the first session becomes current automatically');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/academic-sessions')->post('/settings/academic-sessions', [
            'name' => 'Bad Range',
            'starts_on' => '2026-01-01',
            'ends_on' => '2025-12-31',
        ])->assertSessionHasErrors('ends_on');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_session_name_is_unique_per_school_but_reusable_across_schools(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->post('/settings/academic-sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
        $this->from('/settings/academic-sessions')->post('/settings/academic-sessions', [
            'name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31',
        ])->assertSessionHasErrors('name');

        $this->flushSession();

        // Same name is fine for a different school.
        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);
        $this->post('/settings/academic-sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rowsFor($schoolA->id)->count());
        $this->assertSame(1, $this->rowsFor($schoolB->id)->count());
    }

    public function test_marking_a_session_current_demotes_the_previous_one(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/settings/academic-sessions', ['name' => '2024/2025', 'starts_on' => '2024-09-01', 'ends_on' => '2025-07-31']);
        $this->post('/settings/academic-sessions', ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);

        $first = $this->rowsFor($school->id)->where('name', '2024/2025')->firstOrFail();
        $second = $this->rowsFor($school->id)->where('name', '2025/2026')->firstOrFail();

        $this->assertTrue((bool) $first->is_current);
        $this->assertFalse((bool) $second->is_current);

        $this->patch("/settings/academic-sessions/{$second->id}")->assertRedirect();

        $this->assertFalse((bool) $first->fresh()->is_current);
        $this->assertTrue((bool) $second->fresh()->is_current);
        $this->assertSame(1, $this->rowsFor($school->id)->where('is_current', true)->count());
    }

    public function test_sessions_are_tenant_isolated(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolA);
        $sessionA = AcademicSession::create(['name' => 'A-2025', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);

        // B's admin does not see A's session in the list …
        $this->get('/settings/academic-sessions')->assertOk()->assertDontSee('A-2025');

        // … and cannot make it current (404 — resolved through SchoolScope).
        $this->patch("/settings/academic-sessions/{$sessionA->id}")->assertNotFound();

        $this->assertFalse((bool) $this->rowsFor($schoolA->id)->find($sessionA->id)->is_current);
    }

    public function test_view_only_roles_can_list_but_not_create(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        AcademicSession::create(['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get('/settings/academic-sessions')->assertOk()->assertSee('2025/2026');
        $this->from('/settings/academic-sessions')->post('/settings/academic-sessions', [
            'name' => 'Sneaky', 'starts_on' => '2025-09-01', 'ends_on' => '2026-07-31',
        ])->assertForbidden();
    }

    public function test_roles_without_settings_view_cannot_reach_sessions(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, null] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/academic-sessions')->assertForbidden();
        }
    }
}
