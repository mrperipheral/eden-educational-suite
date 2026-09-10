<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_school_admin_can_view_and_update_settings(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/settings/school')->assertOk()->assertSee('Africa/Lagos');

        $this->patch('/settings/school', [
            'timezone' => 'Africa/Accra',
            'locale' => 'en-GB',
            'contact_email' => 'office@school.example',
            'contact_phone' => '+233200000000',
        ])->assertRedirect(route('settings.school.edit'));

        $settings = SchoolSetting::query()->withoutGlobalScopes()->where('school_id', $school->id)->firstOrFail();
        $this->assertSame('Africa/Accra', $settings->timezone);
        $this->assertSame('office@school.example', $settings->contact_email);
        $this->assertNotNull($settings->completed_at, 'completed_at is stamped on first save');
    }

    public function test_settings_row_is_created_on_first_visit(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->assertDatabaseCount('school_settings', 0);
        $this->get('/settings/school')->assertOk();
        $this->assertDatabaseHas('school_settings', ['school_id' => $school->id, 'timezone' => 'Africa/Lagos']);
    }

    public function test_view_only_roles_can_see_but_not_change_settings(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Bursar] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school')->assertOk()->assertDontSee('name="timezone"', false);
            $this->from('/settings/school')->patch('/settings/school', [
                'timezone' => 'UTC', 'locale' => 'en',
            ])->assertForbidden();
        }
    }

    public function test_roles_without_settings_view_are_denied(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, Role::Student, null] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school')->assertForbidden();
        }
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school')->patch('/settings/school', [
            'timezone' => 'Mars/Olympus_Mons',
            'locale' => 'en',
        ])->assertSessionHasErrors('timezone');
    }

    public function test_settings_are_isolated_between_schools(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        // B's admin sets B's timezone.
        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);
        $this->patch('/settings/school', ['timezone' => 'Europe/London', 'locale' => 'en']);

        $this->flushSession();

        // A's admin edits A — B must be untouched, and A starts from the default.
        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->get('/settings/school')
            ->assertOk()
            ->assertSee('value="Africa/Lagos" selected', false)
            ->assertDontSee('value="Europe/London" selected', false);

        $this->patch('/settings/school', ['timezone' => 'Africa/Accra', 'locale' => 'en']);

        $this->assertSame('Africa/Accra', SchoolSetting::query()->withoutGlobalScopes()
            ->where('school_id', $schoolA->id)->value('timezone'));
        $this->assertSame('Europe/London', SchoolSetting::query()->withoutGlobalScopes()
            ->where('school_id', $schoolB->id)->value('timezone'), 'B untouched');
        $this->assertSame(2, SchoolSetting::query()->withoutGlobalScopes()->count());
    }

    public function test_platform_admin_in_context_can_update_settings(): void
    {
        $school = $this->newSchool();
        $this->actingAsPlatformAdmin($school);

        $this->patch('/settings/school', ['timezone' => 'UTC', 'locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('UTC', SchoolSetting::query()->withoutGlobalScopes()
            ->where('school_id', $school->id)->value('timezone'));
    }
}
