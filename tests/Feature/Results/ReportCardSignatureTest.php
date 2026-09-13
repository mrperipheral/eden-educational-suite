<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\ReportCardConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * M28 security hardening — the report-card principal/class-teacher
 * signature upload/view/delete routes (M15) had zero feature-test coverage
 * despite being structurally identical to the well-tested school-logo
 * feature (`SchoolBrandingTest`). This mirrors that test's pattern: tenant
 * isolation, permission gating, and non-image/undersized rejection — the
 * exact shape the M28 file-security review asked to have proven, not just
 * verified correct by inspection.
 */
class ReportCardSignatureTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function stored(int $schoolId): ReportCardConfiguration
    {
        return ReportCardConfiguration::query()->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNull('academic_session_id')
            ->whereNull('academic_period_id')
            ->firstOrFail();
    }

    public function test_admin_can_upload_a_principal_signature(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
        ])->assertRedirect(route('results.report-card-configuration.edit'));

        $configuration = $this->stored($school->id);
        $this->assertNotNull($configuration->principal_signature_path);
        $this->assertStringStartsWith("report-card-signatures/{$school->id}/", $configuration->principal_signature_path);
        Storage::disk('local')->assertExists($configuration->principal_signature_path);
    }

    public function test_admin_can_upload_a_class_teacher_signature(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/report-card-configuration/class-teacher-signature', [
            'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
        ])->assertRedirect(route('results.report-card-configuration.edit'));

        $configuration = $this->stored($school->id);
        $this->assertNotNull($configuration->class_teacher_signature_path);
        Storage::disk('local')->assertExists($configuration->class_teacher_signature_path);
    }

    public function test_signature_is_served_only_to_authorised_users_and_is_school_scoped(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
        ]);

        // Same school, view-only role (Staff holds result.view): allowed.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Staff);
        $this->get('/results/report-card-configuration/principal-signature')->assertOk();

        // Same school, no result.view permission at all: forbidden.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Parent);
        $this->get('/results/report-card-configuration/principal-signature')->assertForbidden();

        // Another school entirely: its own (empty) configuration — cannot reach school A's file.
        $other = $this->newSchool();
        $this->flushSession();
        $this->actingAsMemberOf($other, Role::SchoolAdmin);
        $this->get('/results/report-card-configuration/principal-signature')->assertNotFound();
    }

    public function test_admin_can_remove_a_signature(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
        ]);

        $path = $this->stored($school->id)->principal_signature_path;

        $this->delete('/results/report-card-configuration/principal-signature')
            ->assertRedirect(route('results.report-card-configuration.edit'));

        $this->assertNull($this->stored($school->id)->principal_signature_path);
        Storage::disk('local')->assertMissing($path);
        $this->get('/results/report-card-configuration/principal-signature')->assertNotFound();
    }

    public function test_replacing_a_signature_deletes_the_previous_file(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/results/report-card-configuration/principal-signature', ['signature' => UploadedFile::fake()->image('one.png', 256, 256)]);
        $first = $this->stored($school->id)->principal_signature_path;

        $this->post('/results/report-card-configuration/principal-signature', ['signature' => UploadedFile::fake()->image('two.png', 256, 256)]);
        $second = $this->stored($school->id)->principal_signature_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/report-card-configuration')->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('signature');

        $this->assertNull(ReportCardConfiguration::query()->withoutGlobalScopes()->where('school_id', $school->id)->first()?->principal_signature_path);
    }

    public function test_undersized_images_are_rejected_by_the_dimensions_rule(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/results/report-card-configuration')->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->image('tiny.png', 10, 10),
        ])->assertSessionHasErrors('signature');
    }

    public function test_view_only_roles_cannot_upload_or_delete_a_signature(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Staff, Role::Teacher] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->post('/results/report-card-configuration/principal-signature', [
                'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
            ])->assertForbidden();
            $this->delete('/results/report-card-configuration/principal-signature')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_bursar_and_parent_and_student_cannot_view_a_signature(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post('/results/report-card-configuration/principal-signature', [
            'signature' => UploadedFile::fake()->image('sig.png', 256, 256),
        ]);

        foreach ([Role::Bursar, Role::Parent, Role::Student] as $role) {
            $this->flushSession();
            $this->actingAsMemberOf($school, $role);
            $this->get('/results/report-card-configuration/principal-signature')->assertForbidden();
        }
    }
}
