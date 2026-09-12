<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;

/**
 * Tenant isolation for the Audit Log (M26, `docs/audit.md`) — a school can
 * only ever see its own audit records; cross-school access and IDOR are
 * blocked on every surface (index/show/export).
 */
class AuditIsolationTest extends AuditTestCase
{
    private function logFor(School $school, string $event = 'test.event'): AuditLog
    {
        return AuditLog::query()->create([
            'school_id' => $school->id,
            'event' => $event,
            'summary' => 'A test event for isolation purposes.',
            'created_at' => now(),
        ]);
    }

    public function test_a_school_never_sees_another_schools_entries_in_the_index(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->logFor($schoolB, 'foreign.event');

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get('/administration/audit-log')->assertOk()->assertDontSee('foreign.event');
    }

    public function test_a_school_cannot_view_another_schools_entry_directly(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $log = $this->logFor($schoolB);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/administration/audit-log/{$log->id}")->assertNotFound();
    }

    public function test_export_never_includes_another_schools_entries(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $this->logFor($schoolA, 'own.event');
        $this->logFor($schoolB, 'foreign.event');

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $content = $this->get('/administration/audit-log/export')->streamedContent();

        $this->assertStringContainsString('own.event', $content);
        $this->assertStringNotContainsString('foreign.event', $content);
    }

    public function test_a_null_school_event_never_appears_in_any_schools_viewer(): void
    {
        $school = School::factory()->create();
        AuditLog::query()->create([
            'school_id' => null,
            'event' => 'auth.login.success',
            'summary' => 'A schoolless login event.',
            'created_at' => now(),
        ]);

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log')->assertOk()->assertDontSee('A schoolless login event.');
    }

    public function test_the_actor_filter_dropdown_never_lists_another_schools_users(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $foreignAdmin = $this->memberOf($schoolB, Role::SchoolAdmin, ['name' => 'Foreign Admin Person']);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get('/administration/audit-log')->assertOk()->assertDontSee('Foreign Admin Person');
    }
}
