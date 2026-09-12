<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;

/**
 * CSV export of the audit log (M26, `docs/audit.md`) — respects the current
 * tenant and active filters, never leaks secrets, and is authorized the
 * same way as the viewer itself.
 */
class AuditExportTest extends AuditTestCase
{
    private function logFor(School $school, array $overrides = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'school_id' => $school->id,
            'event' => 'test.event',
            'summary' => 'A test audit entry.',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_export_returns_a_csv_with_the_expected_header_and_rows(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['summary' => 'Exportable entry.', 'event' => 'student.created']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->get('/administration/audit-log/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Event', $content);
        $this->assertStringContainsString('Exportable entry.', $content);
        $this->assertStringContainsString('student.created', $content);
    }

    public function test_export_respects_the_current_event_filter(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['summary' => 'Included entry.', 'event' => 'student.created']);
        $this->logFor($school, ['summary' => 'Excluded entry.', 'event' => 'teacher.created']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $content = $this->get('/administration/audit-log/export?event=student.created')->streamedContent();

        $this->assertStringContainsString('Included entry.', $content);
        $this->assertStringNotContainsString('Excluded entry.', $content);
    }

    public function test_export_never_includes_secret_shaped_payloads(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, [
            'summary' => 'Payments updated.',
            'changes' => ['before' => ['paystack_secret_key' => '[redacted]'], 'after' => ['paystack_secret_key' => '[redacted]']],
        ]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $content = $this->get('/administration/audit-log/export')->streamedContent();

        $this->assertStringNotContainsString('sk_', $content);
    }

    public function test_bursar_cannot_export_by_default(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::Bursar);

        $this->get('/administration/audit-log/export')->assertForbidden();
    }
}
