<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolBrandingTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function stored(int $schoolId): SchoolSetting
    {
        return SchoolSetting::query()->withoutGlobalScopes()->where('school_id', $schoolId)->firstOrFail();
    }

    public function test_admin_can_upload_a_logo_and_set_a_brand_colour(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch('/settings/school/branding', [
            'brand_color' => '#1D4ED8',
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        ])->assertRedirect(route('settings.school.branding.edit'));

        $settings = $this->stored($school->id);
        $this->assertSame('#1d4ed8', $settings->brand_color, 'colour is normalised to lower case');
        $this->assertNotNull($settings->logo_path);
        $this->assertStringStartsWith("school-logos/{$school->id}/", $settings->logo_path);
        Storage::disk('local')->assertExists($settings->logo_path);
    }

    public function test_logo_is_served_only_to_authorised_users_and_is_school_scoped(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)]);

        // Same school, view-only role: allowed.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Principal);
        $this->get('/settings/school/branding/logo')->assertOk();

        // Same school, no settings-view permission: forbidden.
        $this->flushSession();
        $this->actingAsMemberOf($school, Role::Teacher);
        $this->get('/settings/school/branding/logo')->assertForbidden();

        // Another school entirely: its own (empty) branding — cannot reach school A's file.
        $other = $this->newSchool();
        $this->flushSession();
        $this->actingAsMemberOf($other, Role::SchoolAdmin);
        $this->get('/settings/school/branding/logo')->assertNotFound();
    }

    public function test_admin_can_remove_the_logo(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('logo.png', 256, 256)]);

        $path = $this->stored($school->id)->logo_path;

        $this->delete('/settings/school/branding/logo')->assertRedirect(route('settings.school.branding.edit'));

        $this->assertNull($this->stored($school->id)->logo_path);
        Storage::disk('local')->assertMissing($path);
        $this->get('/settings/school/branding/logo')->assertNotFound();
    }

    public function test_replacing_a_logo_deletes_the_previous_file(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('one.png', 256, 256)]);
        $first = $this->stored($school->id)->logo_path;

        $this->patch('/settings/school/branding', ['logo' => UploadedFile::fake()->image('two.png', 256, 256)]);
        $second = $this->stored($school->id)->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/branding')->patch('/settings/school/branding', [
            'logo' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('school_settings', 0);
    }

    public function test_tiny_images_are_rejected_by_the_dimensions_rule(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/branding')->patch('/settings/school/branding', [
            'logo' => UploadedFile::fake()->image('tiny.png', 20, 20),
        ])->assertSessionHasErrors('logo');
    }

    public function test_invalid_brand_colour_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/branding')->patch('/settings/school/branding', [
            'brand_color' => 'blue',
        ])->assertSessionHasErrors('brand_color');
    }

    public function test_view_only_roles_cannot_change_branding(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Bursar] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school/branding')->assertOk();
            $this->from('/settings/school/branding')->patch('/settings/school/branding', [
                'brand_color' => '#000000',
            ])->assertForbidden();
            $this->delete('/settings/school/branding/logo')->assertForbidden();
        }
    }
}
