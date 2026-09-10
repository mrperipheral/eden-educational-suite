<?php

namespace Tests\Feature\Settings;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\SchoolModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * The Modules administration page and the enable/disable endpoint.
 */
class SchoolModulesTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // -- The catalogue page ------------------------------------------------

    public function test_school_admin_sees_the_module_catalogue(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/settings/school/modules')
            ->assertOk()
            ->assertSee('Academic Management')
            ->assertSee('Student Management')
            ->assertSee('Parent Portal')
            ->assertSee('Planned')
            ->assertSee('Enable'); // toggle controls are present for an admin
    }

    public function test_a_new_school_has_no_rows_and_the_page_reflects_defaults(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/settings/school/modules')->assertOk();

        $this->assertDatabaseCount('school_modules', 0);
    }

    public function test_view_only_roles_see_the_page_without_toggle_controls(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Principal, Role::Bursar] as $role) {
            $this->actingAsMemberOf($school, $role);

            $this->get('/settings/school/modules')
                ->assertOk()
                ->assertSee('Academic Management')
                ->assertDontSee('name="enabled"', false);

            $this->from('/settings/school/modules')
                ->patch('/settings/school/modules/fees', ['enabled' => 0])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('school_modules', 0);
    }

    public function test_roles_without_settings_view_are_denied(): void
    {
        $school = $this->newSchool();

        foreach ([Role::Teacher, Role::Staff, Role::Parent, Role::Student, null] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/settings/school/modules')->assertForbidden();
            $this->patch('/settings/school/modules/fees', ['enabled' => 0])->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/settings/school/modules')->assertRedirect(route('login'));
    }

    // -- Toggling --------------------------------------------------------

    public function test_admin_can_disable_and_re_enable_a_module(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch('/settings/school/modules/attendance', ['enabled' => 0])
            ->assertRedirect(route('settings.school.modules.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('school_modules', [
            'school_id' => $school->id, 'module' => 'attendance', 'enabled' => 0,
        ]);

        $this->patch('/settings/school/modules/attendance', ['enabled' => 1])->assertRedirect();

        $this->assertDatabaseHas('school_modules', [
            'school_id' => $school->id, 'module' => 'attendance', 'enabled' => 1,
        ]);
        $this->assertDatabaseCount('school_modules', 1);
    }

    public function test_enabled_flag_is_required_and_boolean(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/settings/school/modules')
            ->patch('/settings/school/modules/fees', ['enabled' => 'perhaps'])
            ->assertSessionHasErrors('enabled');

        $this->from('/settings/school/modules')
            ->patch('/settings/school/modules/fees', [])
            ->assertSessionHasErrors('enabled');

        $this->assertDatabaseCount('school_modules', 0);
    }

    public function test_unknown_module_identifier_is_a_404(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch('/settings/school/modules/not-a-real-module', ['enabled' => 1])->assertNotFound();

        $this->assertDatabaseCount('school_modules', 0);
    }

    public function test_school_id_in_the_payload_is_ignored(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->patch('/settings/school/modules/fees', ['enabled' => 0, 'school_id' => $b->id])->assertRedirect();

        $this->assertDatabaseHas('school_modules', ['school_id' => $a->id, 'module' => 'fees']);
        $this->assertDatabaseMissing('school_modules', ['school_id' => $b->id]);
    }

    public function test_enabling_a_module_does_not_grant_any_permission(): void
    {
        $school = $this->newSchool();
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch('/settings/school/modules/fees', ['enabled' => 1])->assertRedirect();

        $this->assertFalse(
            $teacher->fresh()->hasPermission(Permission::FinanceManage, $school),
            'module activation must not confer permissions',
        );
    }

    // -- Dependencies ---------------------------------------------------

    public function test_a_module_cannot_be_disabled_while_a_dependent_is_enabled(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // parent-portal (on by default) depends on guardians (on by default).
        $this->from('/settings/school/modules')
            ->patch('/settings/school/modules/guardians', ['enabled' => 0])
            ->assertSessionHasErrors('module');

        $this->assertDatabaseCount('school_modules', 0);
    }

    public function test_a_module_cannot_be_enabled_while_a_dependency_is_disabled(): void
    {
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // Clear the dependent first, then the dependency.
        $this->patch('/settings/school/modules/parent-portal', ['enabled' => 0])->assertRedirect();
        $this->patch('/settings/school/modules/guardians', ['enabled' => 0])->assertRedirect();

        // Now re-enabling parent-portal is blocked: guardians is off.
        $this->from('/settings/school/modules')
            ->patch('/settings/school/modules/parent-portal', ['enabled' => 1])
            ->assertSessionHasErrors('module');

        $this->assertDatabaseHas('school_modules', [
            'school_id' => $school->id, 'module' => 'parent-portal', 'enabled' => 0,
        ]);
    }

    public function test_dependency_checks_are_scoped_to_the_current_school(): void
    {
        // School A disabled its portal + guardians; School B (defaults) must
        // still be blocked from disabling guardians.
        $a = $this->newSchool();
        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->patch('/settings/school/modules/parent-portal', ['enabled' => 0])->assertRedirect();
        $this->patch('/settings/school/modules/guardians', ['enabled' => 0])->assertRedirect();

        $this->flushSession();
        $this->app->forgetScopedInstances();

        $b = $this->newSchool();
        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->from('/settings/school/modules')
            ->patch('/settings/school/modules/guardians', ['enabled' => 0])
            ->assertSessionHasErrors('module');
    }

    // -- Isolation & platform admin -----------------------------------

    public function test_module_state_is_isolated_between_schools(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->patch('/settings/school/modules/timetable', ['enabled' => 1])->assertRedirect();

        $this->flushSession();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->get('/settings/school/modules')->assertOk();
        $this->patch('/settings/school/modules/fees', ['enabled' => 0])->assertRedirect();

        $this->assertDatabaseHas('school_modules', ['school_id' => $b->id, 'module' => 'timetable', 'enabled' => 1]);
        $this->assertDatabaseHas('school_modules', ['school_id' => $a->id, 'module' => 'fees', 'enabled' => 0]);
        $this->assertDatabaseMissing('school_modules', ['school_id' => $a->id, 'module' => 'timetable']);
        $this->assertDatabaseMissing('school_modules', ['school_id' => $b->id, 'module' => 'fees']);
        $this->assertSame(2, SchoolModule::query()->withoutGlobalScopes()->count());
    }

    public function test_platform_admin_manages_modules_only_within_a_school_context(): void
    {
        $school = $this->newSchool();

        $this->actingAsPlatformAdmin();
        $this->get('/settings/school/modules')->assertRedirect(route('school-context.create'));

        $this->flushSession();
        $this->app->forgetScopedInstances();

        $this->actingAsPlatformAdmin($school);
        $this->patch('/settings/school/modules/timetable', ['enabled' => 1])->assertRedirect();

        $this->assertDatabaseHas('school_modules', [
            'school_id' => $school->id, 'module' => 'timetable', 'enabled' => 1,
        ]);
    }
}
