<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * M29.5 — the sidebar's authorization filtering and the "See more" collapse.
 * These check the *rendered* nav only (never a routing or permission-system
 * concern in themselves) — the underlying authorization is already proven by
 * each domain module's own tests; this file only proves the nav reflects it.
 */
class NavigationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_bursars_nav_never_shows_attendance_or_results_management(): void
    {
        // Bursar holds StudentView/StaffView (needed to look students/staff up
        // on a fee statement), so those DO appear — but attendance, results,
        // and audit are genuinely outside the role (App\Enums\Role::Bursar).
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Bursar);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('attendance.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('results.runs.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('audit-log.index').'"', $html);
        $this->assertStringContainsString('href="'.route('fees.index').'"', $html);
    }

    public function test_a_school_admins_long_nav_has_a_see_more_section_that_still_contains_every_authorized_item(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        // A School Admin holds every permission, so the nav is long enough
        // to need the collapse.
        $this->assertStringContainsString('See more', $html);
        // But every authorized route is still present in the markup (the
        // collapse only hides visually, via Alpine x-show, not from the DOM
        // — see resources/views/components/layouts/authenticated.blade.php).
        $this->assertStringContainsString('href="'.route('settings.school.edit').'"', $html);
        $this->assertStringContainsString('href="'.route('audit-log.index').'"', $html);
    }

    public function test_a_parents_short_nav_has_no_see_more_section(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Parent);

        $html = $this->get('/parent')->assertOk()->getContent();

        $this->assertStringNotContainsString('See more', $html);
    }

    public function test_sidebar_shows_the_schools_own_identity_and_the_platform_wordmark(): void
    {
        $school = $this->newSchool(['name' => 'Greenfield College']);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Greenfield College', $html);
        $this->assertStringContainsString('School Portal', $html);
        $this->assertStringContainsString('Powered by Eden Education Suite', $html);
    }

    public function test_platform_admin_without_a_school_context_sees_platform_branding_not_a_school_name(): void
    {
        $admin = $this->actingAsPlatformAdmin();

        $html = $this->get('/admin/schools')->assertOk()->getContent();

        $this->assertStringContainsString('Eden Education Suite', $html);
        $this->assertStringContainsString('Platform Administration', $html);
        $this->assertStringNotContainsString('School Portal', $html);
    }

    public function test_platform_admin_sidebar_shows_the_actual_eden_logo_image(): void
    {
        $this->actingAsPlatformAdmin();

        $html = $this->get('/admin/schools')->assertOk()->getContent();

        $this->assertStringContainsString('branding/eden-education-suite-logo.png', $html);
    }

    // -- M29.5: the sidebar washes in the school's own primary colour -----

    public function test_the_sidebar_uses_the_schools_own_primary_colour_when_configured(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['brand_color' => '#123456']);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('background-color: #123456', $html);
    }

    public function test_the_sidebar_stays_neutral_white_with_no_colour_configured(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('background-color: #', $html);
    }

    public function test_school_a_and_school_b_sidebar_colours_never_leak_into_each_other(): void
    {
        $a = $this->newSchool();
        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['brand_color' => '#111111']);
        $this->flushSession();

        $b = $this->newSchool();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->patch('/settings/school/branding', ['brand_color' => '#222222']);

        $html = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('background-color: #222222', $html);
        $this->assertStringNotContainsString('background-color: #111111', $html);
    }

    // -- M29.5: item 4 — no "Powered by" line on Parent/Student pages -----

    public function test_the_powered_by_line_never_shows_on_parent_or_student_pages(): void
    {
        $school = $this->newSchool();

        $this->actingAsMemberOf($school, Role::Parent);
        $this->assertStringNotContainsString(
            'Powered by Eden Education Suite',
            $this->get('/parent')->assertOk()->getContent(),
        );

        $this->flushSession();

        $this->actingAsMemberOf($school, Role::Student);
        $this->assertStringNotContainsString(
            'Powered by Eden Education Suite',
            $this->get('/student')->assertOk()->getContent(),
        );
    }
}
