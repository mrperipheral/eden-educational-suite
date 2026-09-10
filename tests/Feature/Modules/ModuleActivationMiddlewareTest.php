<?php

namespace Tests\Feature\Modules;

use App\Enums\Module;
use App\Enums\Role;
use App\Models\SchoolModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * The `module:` route middleware — the reusable seam future domain modules use.
 * It gates on the module flag only; it grants nothing.
 */
class ModuleActivationMiddlewareTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'verified', 'active', 'tenant', 'module:fees'])
            ->get('/_test/fees-area', fn () => 'fees-area');
    }

    public function test_the_request_passes_when_the_module_is_enabled(): void
    {
        $school = $this->newSchool(); // fees is on by default
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/_test/fees-area')->assertOk()->assertSee('fees-area');
    }

    public function test_the_route_behaves_as_missing_when_the_module_is_disabled(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => Module::Fees->value, 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/_test/fees-area')->assertNotFound();
    }

    public function test_the_gate_does_not_grant_permissions(): void
    {
        // A Teacher holds no finance permission, but the module gate only asks
        // "is Fees switched on for this school?" — which it is, by default.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::Teacher);

        $this->get('/_test/fees-area')->assertOk();
    }

    public function test_it_requires_a_tenant_context(): void
    {
        $user = $this->stranger();
        $user->schools()->attach([$this->newSchool()->id, $this->newSchool()->id]);

        $this->actingAs($user)->get('/_test/fees-area')->assertRedirect(route('school-context.create'));
    }
}
