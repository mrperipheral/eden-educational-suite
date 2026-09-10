<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * School settings — profile section plus the authorization / tenant-isolation
 * rules shared by every section (branding and regional have their own files).
 */
class SchoolSettingsTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function stored(int $schoolId): SchoolSetting
    {
        return SchoolSetting::query()->withoutGlobalScopes()->where('school_id', $schoolId)->firstOrFail();
    }

    public function test_school_admin_can_view_and_update_the_profile(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/settings/school')->assertOk()->assertSee($school->name);

        $this->patch('/settings/school', [
            'contact_email' => 'office@school.example',
            'contact_phone' => '+234 801 000 0000',
            'website_url' => 'https://school.example',
            'address_line1' => '1 Broad Street',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'postal_code' => '100001',
            'country' => 'ng',
        ])->assertRedirect(route('settings.school.edit'));

        $settings = $this->stored($school->id);
        $this->assertSame('office@school.example', $settings->contact_email);
        $this->assertSame('https://school.example', $settings->website_url);
        $this->assertSame('Lagos', $settings->city);
        $this->assertSame('NG', $settings->country, 'country is normalised to upper case');
        $this->assertNotNull($settings->completed_at, 'saving stamps the onboarding-review flag');
    }

    public function test_settings_row_is_created_on_first_visit(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->assertDatabaseCount('school_settings', 0);
        $this->get('/settings/school')->assertOk();
        $this->assertDatabaseHas('school_settings', [
            'school_id' => $school->id,
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
        ]);
    }

    public function test_view_only_roles_can_see_but_not_change_settings(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Bursar] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school')->assertOk()->assertDontSee('name="contact_email"', false);
            $this->from('/settings/school')->patch('/settings/school', [
                'contact_email' => 'sneaky@school.example',
            ])->assertForbidden();
        }

        $this->assertNull($this->stored($school->id)->contact_email);
    }

    public function test_roles_without_settings_view_are_denied(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, Role::Student, null] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school')->assertForbidden();
            $this->get('/settings/school/branding')->assertForbidden();
            $this->get('/settings/school/regional')->assertForbidden();
        }
    }

    public function test_invalid_profile_input_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school')->patch('/settings/school', [
            'contact_email' => 'not-an-email',
            'website_url' => 'ftp://school.example',
            'country' => 'ZZ',
        ])->assertSessionHasErrors(['contact_email', 'website_url', 'country']);
    }

    public function test_protected_columns_cannot_be_mass_assigned(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get('/settings/school')->assertOk();

        $this->patch('/settings/school', [
            'contact_email' => 'office@school.example',
            'school_id' => 999999,
            'logo_path' => 'school-logos/evil.png',
            'completed_at' => null,
        ])->assertRedirect();

        $settings = $this->stored($school->id);
        $this->assertSame($school->id, $settings->school_id);
        $this->assertNull($settings->logo_path);
        $this->assertNotNull($settings->completed_at);
    }

    public function test_settings_are_isolated_between_schools(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);
        $this->patch('/settings/school', ['contact_email' => 'b@school.example', 'city' => 'Kano']);

        $this->flushSession();

        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->get('/settings/school')->assertOk()->assertDontSee('b@school.example');
        $this->patch('/settings/school', ['contact_email' => 'a@school.example', 'city' => 'Lagos']);

        $this->assertSame('a@school.example', $this->stored($schoolA->id)->contact_email);
        $this->assertSame('b@school.example', $this->stored($schoolB->id)->contact_email, 'B untouched');
        $this->assertSame(2, SchoolSetting::query()->withoutGlobalScopes()->count());
    }

    public function test_platform_admin_operates_only_through_tenant_context(): void
    {
        $school = $this->newSchool();

        // No active school selected — denied.
        $this->actingAsPlatformAdmin();
        $this->get('/settings/school')->assertRedirect(route('school-context.create'));

        // Inside the school's context — allowed, and scoped to that school.
        $this->flushSession();
        $this->actingAsPlatformAdmin($school);
        $this->patch('/settings/school', ['contact_email' => 'platform@school.example'])->assertRedirect();

        $this->assertSame('platform@school.example', $this->stored($school->id)->contact_email);
    }
}
