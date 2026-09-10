<?php

namespace Tests\Feature\Modules;

use App\Enums\Module;
use App\Models\SchoolModule;
use App\Support\Modules\SchoolModules;
use App\Support\Tenancy\Exceptions\MissingTenantContextException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * The request-scoped module-activation resolver: defaults, stored overrides,
 * tenant isolation, fail-safe behaviour and the "read once per request" promise.
 */
class SchoolModulesTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private function resolver(): SchoolModules
    {
        return app(SchoolModules::class);
    }

    public function test_a_school_with_no_rows_uses_catalogue_defaults(): void
    {
        $this->enterSchool($this->newSchool());

        $modules = $this->resolver();

        $this->assertTrue($modules->enabled(Module::Students));
        $this->assertTrue($modules->enabled(Module::Fees));
        $this->assertFalse($modules->enabled(Module::Cbt));
        $this->assertSame(Module::defaults(), $modules->states());
    }

    public function test_a_stored_override_wins_over_the_default(): void
    {
        $school = $this->enterSchool($this->newSchool());
        SchoolModule::query()->create(['module' => Module::Fees->value, 'enabled' => false]);
        SchoolModule::query()->create(['module' => Module::Cbt->value, 'enabled' => true]);

        $this->app->forgetScopedInstances();
        $this->enterSchool($school);
        $modules = $this->resolver();

        $this->assertFalse($modules->enabled(Module::Fees));
        $this->assertTrue($modules->enabled(Module::Cbt));
        $this->assertTrue($modules->enabled(Module::Students), 'untouched module still uses its default');
    }

    public function test_set_upserts_a_single_row_and_updates_the_cache(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $modules = $this->resolver();

        $modules->set(Module::Fees, false);
        $modules->set(Module::Fees, true);
        $modules->set(Module::Fees, false);

        $this->assertFalse($modules->enabled(Module::Fees), 'in-memory cache reflects the write');
        $this->assertSame(1, SchoolModule::query()->withoutGlobalScopes()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('school_modules', [
            'school_id' => $school->id, 'module' => 'fees', 'enabled' => false,
        ]);
    }

    public function test_unknown_module_rows_are_ignored_and_do_not_break_resolution(): void
    {
        $school = $this->enterSchool($this->newSchool());

        DB::table('school_modules')->insert([
            'school_id' => $school->id,
            'module' => 'retired-module',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->forgetScopedInstances();
        $this->enterSchool($school);
        $modules = $this->resolver();

        $this->assertSame(Module::defaults(), $modules->states());
        $this->assertArrayNotHasKey('retired-module', $modules->states());
    }

    public function test_module_state_is_read_from_the_database_once_per_request(): void
    {
        $school = $this->enterSchool($this->newSchool());
        SchoolModule::query()->create(['module' => Module::Fees->value, 'enabled' => false]);

        $this->app->forgetScopedInstances();
        $this->enterSchool($school);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $modules = $this->resolver();
        $modules->enabled(Module::Fees);
        $modules->enabled(Module::Students);
        $modules->enabled(Module::Fees);
        $modules->states();

        DB::disableQueryLog();

        $reads = collect(DB::getQueryLog())
            ->filter(fn ($entry) => str_contains($entry['query'], 'school_modules'))
            ->count();

        $this->assertSame(1, $reads, 'the overrides table should be queried exactly once');
    }

    public function test_module_state_is_isolated_between_schools(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $this->resolver()->set(Module::Timetable, true);

        $this->app->forgetScopedInstances();
        $this->enterSchool($b);

        $this->assertFalse($this->resolver()->enabled(Module::Timetable), "B does not see A's override");
    }

    public function test_resolution_fails_closed_without_a_tenant_context(): void
    {
        $this->expectException(MissingTenantContextException::class);

        $this->resolver()->enabled(Module::Students);
    }
}
