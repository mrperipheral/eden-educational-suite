<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;

/**
 * Role-based authorization for the Audit Log (M26, `docs/audit.md`):
 * School Admin and Principal can access it; every other role cannot unless
 * explicitly granted. There is no route to modify or delete an entry at all
 * — verified here by asserting the only routes that exist are read-only.
 */
class AuditAuthorizationTest extends AuditTestCase
{
    public function test_school_admin_can_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log')->assertOk();
    }

    public function test_principal_can_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Principal);

        $this->get('/administration/audit-log')->assertOk();
    }

    public function test_teacher_cannot_view_the_audit_log_by_default(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Teacher);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_bursar_cannot_view_the_audit_log_by_default(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Bursar);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_staff_cannot_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Staff);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_parent_cannot_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Parent);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_student_cannot_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Student);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_role_less_member_cannot_view_the_audit_log(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, null);

        $this->get('/administration/audit-log')->assertForbidden();
    }

    public function test_a_non_authorized_role_cannot_view_an_entry_detail(): void
    {
        $school = School::factory()->create();
        $log = AuditLog::query()->create([
            'school_id' => $school->id, 'event' => 'test.event',
            'summary' => 'Something happened.', 'created_at' => now(),
        ]);
        $this->actingAsRole($school, Role::Teacher);

        $this->get("/administration/audit-log/{$log->id}")->assertForbidden();
    }

    public function test_no_route_exists_to_edit_or_delete_an_audit_entry(): void
    {
        $school = School::factory()->create();
        $log = AuditLog::query()->create([
            'school_id' => $school->id, 'event' => 'test.event',
            'summary' => 'Something happened.', 'created_at' => now(),
        ]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        // The only route registered for this URI is the read-only `show`
        // (GET) — PATCH/PUT/DELETE match no route action at all, so the
        // router itself rejects them (405), before authorization or a
        // controller ever runs. There is no edit/destroy feature to gate.
        $this->patch("/administration/audit-log/{$log->id}", ['summary' => 'tampered'])->assertStatus(405);
        $this->put("/administration/audit-log/{$log->id}", ['summary' => 'tampered'])->assertStatus(405);
        $this->delete("/administration/audit-log/{$log->id}")->assertStatus(405);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'summary' => 'Something happened.']);
    }
}
