<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;

/**
 * Audit creation basics (M26, `docs/audit.md`): important actions generate
 * audit records with the correct actor, school, affected entity, and
 * before/after changes where expected; sensitive fields are excluded.
 */
class AuditLogTest extends AuditTestCase
{
    public function test_creating_a_student_writes_an_audit_record(): void
    {
        $school = School::factory()->create();
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/students', [
            'first_name' => 'Chidi',
            'last_name' => 'Okafor',
            'admission_number' => 'STU-9001',
            'date_of_birth' => '2015-04-02',
            'gender' => 'male',
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'student.created')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($admin->name, $log->actor_name);
        $this->assertSame('App\Models\Student', $log->auditable_type);
        $this->assertNotNull($log->auditable_id);
        $this->assertStringContainsString('Chidi', $log->auditable_label);
        $this->assertStringContainsString($admin->name, $log->summary);
    }

    public function test_a_students_status_change_captures_before_and_after(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/students', [
            'first_name' => 'Ada', 'last_name' => 'Bello', 'admission_number' => 'STU-9002',
            'date_of_birth' => '2014-01-01', 'gender' => 'female',
        ]);
        $studentId = Student::query()->latest('id')->first()->id;

        $this->patch("/students/{$studentId}/status", ['status' => 'inactive'])->assertRedirect();

        $log = AuditLog::query()->where('event', 'student.status_changed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame(['status' => 'active'], $log->changes['before']);
        $this->assertSame(['status' => 'inactive'], $log->changes['after']);
    }

    public function test_creating_a_teacher_writes_an_audit_record(): void
    {
        $school = School::factory()->create();
        $admin = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/teachers', [
            'first_name' => 'Ngozi', 'last_name' => 'Umeh', 'employee_number' => 'EMP-9001',
            'email' => 'ngozi.audit@example.test', 'phone' => '+234 803 111 2222',
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'teacher.created')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($school->id, $log->school_id);
        $this->assertSame($admin->id, $log->actor_id);
    }

    public function test_creating_a_guardian_writes_an_audit_record(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/guardians', [
            'first_name' => 'Amaka', 'last_name' => 'Okonkwo',
            'phone' => '+234 803 111 2222', 'email' => 'amaka.audit@example.test',
        ])->assertRedirect();

        $this->assertNotNull(AuditLog::query()->where('event', 'guardian.created')->latest('id')->first());
    }

    public function test_creating_an_academic_session_writes_an_audit_record(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/academic/sessions', [
            'name' => '2030/2031', 'starts_on' => '2030-09-01', 'ends_on' => '2031-07-31',
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'academic_session.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('2030/2031', $log->auditable_label);
    }

    public function test_recording_a_fee_payment_writes_an_audit_record(): void
    {
        $school = School::factory()->create();
        $this->enterSchool($school);
        $student = Student::factory()->create();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/fees/students/{$student->id}/payments", [
            'amount' => '5000.00',
            'payment_date' => now()->toDateString(),
            'reference' => 'PAY-AUDIT-001',
            'method' => 'cash',
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'fee_payment.recorded')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('PAY-AUDIT-001', $log->auditable_label);
    }

    public function test_module_toggle_captures_before_and_after(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch('/settings/school/modules/fees', ['enabled' => 0])->assertRedirect();

        $log = AuditLog::query()->where('event', 'module.toggled')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['enabled' => true], $log->changes['before']);
        $this->assertSame(['enabled' => false], $log->changes['after']);
    }

    public function test_paystack_secret_key_is_redacted_from_settings_audit_payload(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch('/settings/school/payments', [
            'paystack_enabled' => 1,
            'paystack_public_key' => 'pk_test_public',
            'paystack_secret_key' => 'sk_test_super_secret_value',
            'paystack_test_mode' => 1,
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'settings.payments_updated')->latest('id')->first();

        $this->assertNotNull($log);
        $payload = json_encode($log->changes);
        $this->assertStringNotContainsString('sk_test_super_secret_value', $payload);
        $this->assertTrue($log->changes['after']['paystack_key_was_rotated']);
    }

    public function test_profile_settings_update_redacts_any_secret_shaped_column(): void
    {
        $school = School::factory()->create();
        $this->enterSchool($school);
        $settings = $school->settings()->firstOrCreate([]);
        $settings->paystack_secret_key = 'sk_pre_existing_secret';
        $settings->save();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->patch('/settings/school', ['contact_email' => 'office@example.test'])->assertRedirect();

        $log = AuditLog::query()->where('event', 'settings.profile_updated')->latest('id')->first();
        $this->assertNotNull($log);
        $payload = json_encode($log->changes);
        $this->assertStringNotContainsString('sk_pre_existing_secret', $payload);
        $this->assertSame('[redacted]', $log->changes['before']['paystack_secret_key']);
        $this->assertSame('[redacted]', $log->changes['after']['paystack_secret_key']);
    }

    public function test_password_is_never_recorded_in_any_audit_payload(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'a-new-strong-password-1',
            'password_confirmation' => 'a-new-strong-password-1',
        ]);

        $log = $this->latestAuditFor('auth.password.changed');
        $this->assertNotNull($log);
        $payload = json_encode($log->toArray());
        $this->assertStringNotContainsString('a-new-strong-password-1', $payload);
    }

    public function test_a_question_status_change_is_audited(): void
    {
        $school = School::factory()->create();
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'cbt', 'enabled' => true]);
        $subject = Subject::factory()->create();
        $question = new Question(['subject_id' => $subject->id, 'question_text' => 'Audit test question?', 'marks' => 1]);
        $question->type = 'multiple_choice';
        $question->save();
        $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 1]);
        $question->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 2]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);
        $this->post("/cbt/questions/{$question->id}/archive")->assertRedirect();

        $log = AuditLog::query()->where('event', 'question.status_changed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['status' => 'active'], $log->changes['before']);
        $this->assertSame(['status' => 'archived'], $log->changes['after']);
    }

    public function test_an_entry_assessment_record_creation_is_audited(): void
    {
        $school = School::factory()->create();
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'entry-assessment', 'enabled' => true]);
        $level = AcademicLevel::factory()->create();
        $subject = Subject::factory()->create();
        $level->subjects()->attach($subject->id, ['school_id' => $school->id]);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/entry-assessments', [
            'candidate_name' => 'Audit Candidate',
            'academic_level_id' => $level->id,
            'subject_id' => $subject->id,
            'assessed_on' => now()->toDateString(),
            'max_score' => 100,
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'entry_assessment.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Audit Candidate', $log->auditable_label);
    }
}
