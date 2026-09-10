<?php

namespace Tests\Feature\Settings;

use App\Enums\DateFormat;
use App\Enums\Role;
use App\Enums\Weekday;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SchoolRegionalTest extends TestCase
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

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'timezone' => 'Africa/Accra',
            'locale' => 'en-GB',
            'currency' => 'ghs',
            'date_format' => 'm/d/Y',
            'week_starts_on' => (string) Weekday::Sunday->value,
            'academic_year_start_month' => '1',
        ], $overrides);
    }

    public function test_admin_can_update_regional_settings(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/settings/school/regional')->assertOk()->assertSee('Africa/Lagos');

        $this->patch('/settings/school/regional', $this->validPayload())
            ->assertRedirect(route('settings.school.regional.edit'));

        $settings = $this->stored($school->id);
        $this->assertSame('Africa/Accra', $settings->timezone);
        $this->assertSame('en-GB', $settings->locale);
        $this->assertSame('GHS', $settings->currency, 'currency is normalised to upper case');
        $this->assertSame(DateFormat::MonthDayYear, $settings->date_format);
        $this->assertSame(Weekday::Sunday, $settings->week_starts_on);
        $this->assertSame(1, $settings->academic_year_start_month);
        $this->assertNotNull($settings->completed_at);
    }

    public function test_invalid_regional_values_are_rejected(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/regional')->patch('/settings/school/regional', $this->validPayload([
            'timezone' => 'Mars/Olympus_Mons',
            'currency' => 'ZZZ',
            'date_format' => 'unix',
            'week_starts_on' => '9',
            'academic_year_start_month' => '13',
        ]))->assertSessionHasErrors(['timezone', 'currency', 'date_format', 'week_starts_on', 'academic_year_start_month']);
    }

    public function test_regional_fields_are_required(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/regional')->patch('/settings/school/regional', [])
            ->assertSessionHasErrors(['timezone', 'locale', 'currency', 'date_format', 'week_starts_on', 'academic_year_start_month']);
    }

    public function test_view_only_roles_cannot_change_regional_settings(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Bursar] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school/regional')->assertOk()->assertDontSee('name="timezone"', false);
            $this->from('/settings/school/regional')->patch('/settings/school/regional', $this->validPayload())
                ->assertForbidden();
        }

        $this->assertSame('Africa/Lagos', $this->stored($school->id)->timezone);
    }

    public function test_regional_settings_are_isolated_between_schools(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolB, Role::SchoolAdmin);
        $this->patch('/settings/school/regional', $this->validPayload(['timezone' => 'Europe/London']));

        $this->flushSession();
        $this->actingAsMemberOf($schoolA, Role::SchoolAdmin);
        $this->patch('/settings/school/regional', $this->validPayload(['timezone' => 'Africa/Accra']));

        $this->assertSame('Africa/Accra', $this->stored($schoolA->id)->timezone);
        $this->assertSame('Europe/London', $this->stored($schoolB->id)->timezone, 'B untouched');
    }

    public function test_platform_admin_in_context_can_update_regional_settings(): void
    {
        $school = $this->newSchool();
        $this->actingAsPlatformAdmin($school);

        $this->patch('/settings/school/regional', $this->validPayload(['timezone' => 'UTC']))->assertRedirect();

        $this->assertSame('UTC', $this->stored($school->id)->timezone);
    }
}
