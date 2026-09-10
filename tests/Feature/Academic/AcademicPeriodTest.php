<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\School;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class AcademicPeriodTest extends AcademicTestCase
{
    private function newAcademicSession(School $school): AcademicSession
    {
        $this->enterSchool($school);
        $session = AcademicSession::factory()->current()->create();
        $this->app->forgetScopedInstances();

        return $session;
    }

    private function periods(int $schoolId)
    {
        return AcademicPeriod::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_add_periods_and_the_first_becomes_current(): void
    {
        $school = $this->newSchool();
        $session = $this->newAcademicSession($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/academic/sessions/{$session->id}/periods", [
            'name' => 'First Term', 'starts_on' => '2025-09-15', 'ends_on' => '2025-12-12', 'position' => 1,
        ])->assertRedirect(route('academic.sessions.show', $session->id));

        $period = $this->periods($school->id)->firstOrFail();
        $this->assertSame($session->id, $period->academic_session_id);
        $this->assertSame($school->id, $period->school_id);
        $this->assertTrue((bool) $period->is_current);
    }

    public function test_a_school_may_configure_any_number_of_periods(): void
    {
        $school = $this->newSchool();
        $session = $this->newAcademicSession($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        foreach (range(1, 5) as $i) {
            $this->post("/academic/sessions/{$session->id}/periods", [
                'name' => "Period {$i}", 'starts_on' => "2025-0{$i}-01", 'ends_on' => "2025-0{$i}-28", 'position' => $i,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(5, $this->periods($school->id)->count());
    }

    public function test_period_name_and_order_are_unique_within_a_session(): void
    {
        $school = $this->newSchool();
        $session = $this->newAcademicSession($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/academic/sessions/{$session->id}/periods", [
            'name' => 'First Term', 'starts_on' => '2025-09-15', 'ends_on' => '2025-12-12', 'position' => 1,
        ]);

        $this->from(route('academic.sessions.show', $session->id))
            ->post("/academic/sessions/{$session->id}/periods", [
                'name' => 'First Term', 'starts_on' => '2026-01-06', 'ends_on' => '2026-04-03', 'position' => 2,
            ])->assertSessionHasErrors('name');

        $this->from(route('academic.sessions.show', $session->id))
            ->post("/academic/sessions/{$session->id}/periods", [
                'name' => 'Second Term', 'starts_on' => '2026-01-06', 'ends_on' => '2026-04-03', 'position' => 1,
            ])->assertSessionHasErrors('position');

        $this->assertSame(1, $this->periods($school->id)->count());
    }

    public function test_the_same_period_name_is_fine_in_a_different_session(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $s1 = AcademicSession::factory()->create(['name' => '2024/2025']);
        $s2 = AcademicSession::factory()->create(['name' => '2025/2026']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/academic/sessions/{$s1->id}/periods", ['name' => 'First Term', 'starts_on' => '2024-09-15', 'ends_on' => '2024-12-12', 'position' => 1])
            ->assertSessionHasNoErrors();
        $this->post("/academic/sessions/{$s2->id}/periods", ['name' => 'First Term', 'starts_on' => '2025-09-15', 'ends_on' => '2025-12-12', 'position' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->periods($school->id)->count());
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $school = $this->newSchool();
        $session = $this->newAcademicSession($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('academic.sessions.show', $session->id))
            ->post("/academic/sessions/{$session->id}/periods", [
                'name' => 'Bad', 'starts_on' => '2025-12-12', 'ends_on' => '2025-09-15', 'position' => 1,
            ])->assertSessionHasErrors('ends_on');
    }

    public function test_only_one_period_is_current_within_a_session(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $session = AcademicSession::factory()->current()->create();
        $p1 = $session->periods()->create(['name' => 'T1', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1]);
        $p2 = $session->periods()->create(['name' => 'T2', 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-01', 'position' => 2]);
        $p1->makeCurrent();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->put("/academic/periods/{$p2->id}/current")->assertRedirect();

        $this->assertFalse((bool) $p1->fresh()->is_current);
        $this->assertTrue((bool) $p2->fresh()->is_current);
        $this->assertSame(1, $this->periods($school->id)->where('is_current', true)->count());
    }

    public function test_current_periods_in_different_sessions_do_not_collide(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        $s1 = AcademicSession::factory()->create(['name' => '2024/2025']);
        $s2 = AcademicSession::factory()->create(['name' => '2025/2026']);
        $a = $s1->periods()->create(['name' => 'A', 'starts_on' => '2024-09-01', 'ends_on' => '2024-12-01', 'position' => 1]);
        $b = $s2->periods()->create(['name' => 'B', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1]);
        $a->makeCurrent();
        $b->makeCurrent();

        $this->assertTrue((bool) $a->fresh()->is_current);
        $this->assertTrue((bool) $b->fresh()->is_current);
        $this->assertSame(2, $this->periods($school->id)->where('is_current', true)->count());
    }

    public function test_view_roles_cannot_add_periods_and_denied_roles_get_403(): void
    {
        $school = $this->newSchool();
        $session = $this->newAcademicSession($school);

        foreach ($this->academicRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/academic/sessions/{$session->id}")->assertOk();
            $this->from(route('academic.sessions.show', $session->id))
                ->post("/academic/sessions/{$session->id}/periods", ['name' => 'X', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1])
                ->assertForbidden();
            $this->flushSession();
        }

        foreach ($this->academicRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get("/academic/sessions/{$session->id}")->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_periods_are_tenant_isolated(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $sessionA = $this->newAcademicSession($a);
        $this->enterSchool($a);
        $periodA = $sessionA->periods()->create(['name' => 'A-term', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        // Cannot add a period to school A's session …
        $this->post("/academic/sessions/{$sessionA->id}/periods", ['name' => 'B-term', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1])
            ->assertNotFound();
        // … nor edit or promote school A's period.
        $this->get("/academic/periods/{$periodA->id}/edit")->assertNotFound();
        $this->patch("/academic/periods/{$periodA->id}", ['name' => 'x', 'starts_on' => '2025-09-01', 'ends_on' => '2025-12-01', 'position' => 1])->assertNotFound();
        $this->put("/academic/periods/{$periodA->id}/current")->assertNotFound();

        $this->assertSame(0, $this->periods($b->id)->count());
        $this->assertSame('A-term', $periodA->fresh()->name);
    }

    public function test_school_ownership_is_immutable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->enterSchool($a);
        $period = AcademicPeriod::factory()->create();

        $this->expectException(TenantMismatchException::class);
        $period->school_id = $b->id;
        $period->save();
    }
}
