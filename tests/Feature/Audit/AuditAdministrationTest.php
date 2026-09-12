<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;

/**
 * User/access administration audit events (M26, `docs/audit.md`): role
 * changes, membership creation/removal, and account status changes all
 * produce an audit record.
 */
class AuditAdministrationTest extends AuditTestCase
{
    public function test_adding_a_member_is_audited(): void
    {
        $school = School::factory()->create();
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);
        $newcomer = User::factory()->create();

        $this->post('/members', ['email' => $newcomer->email, 'role' => Role::Teacher->value])
            ->assertRedirect(route('members.index'));

        $log = AuditLog::query()->where('event', 'member.created')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($newcomer->id, $log->auditable_id);
        $this->assertSame(['role' => Role::Teacher->value], $log->changes['after']);
    }

    public function test_changing_a_members_role_is_audited_with_before_and_after(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->patch("/members/{$teacher->id}", ['role' => Role::Principal->value])->assertRedirect();

        $log = AuditLog::query()->where('event', 'member.role_changed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($teacher->id, $log->auditable_id);
        $this->assertSame(['role' => Role::Teacher->value], $log->changes['before']);
        $this->assertSame(['role' => Role::Principal->value], $log->changes['after']);
    }

    public function test_removing_a_member_is_audited(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->delete("/members/{$teacher->id}")->assertRedirect();

        $log = AuditLog::query()->where('event', 'member.removed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($teacher->id, $log->auditable_id);
        $this->assertSame(['role' => Role::Teacher->value], $log->changes['before']);
    }

    public function test_a_teachers_status_change_is_audited(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/teachers', [
            'first_name' => 'Ngozi', 'last_name' => 'Umeh', 'employee_number' => 'EMP-9002',
            'email' => 'ngozi.status@example.test', 'phone' => '+234 803 111 2222',
        ]);
        $teacherId = Teacher::query()->latest('id')->first()->id;

        $this->patch("/teachers/{$teacherId}/status", ['status' => 'suspended'])->assertRedirect();

        $log = AuditLog::query()->where('event', 'teacher.status_changed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['status' => 'active'], $log->changes['before']);
        $this->assertSame(['status' => 'suspended'], $log->changes['after']);
    }

    public function test_a_platform_admin_creating_a_school_is_audited(): void
    {
        $platformAdmin = $this->actingAsPlatformAdmin();

        $this->post('/admin/schools', ['name' => 'Newly Audited Academy'])->assertRedirect();

        $school = School::query()->where('name', 'Newly Audited Academy')->firstOrFail();
        $log = AuditLog::query()->where('event', 'school.created')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($platformAdmin->id, $log->actor_id);
        $this->assertSame('Newly Audited Academy', $log->auditable_label);
    }
}
