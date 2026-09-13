<?php

namespace Tests\Feature\Reports;

use App\Enums\Module;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Teacher;
use App\Reports\PlatformReport;

/**
 * `PlatformReport` (M27 §13) — genuinely cross-school aggregates only,
 * confined to `TenantContext::runWithoutScope()`. Kept deliberately
 * aggregated: counts only, no per-school PII beyond the school's own name.
 */
class PlatformReportTest extends ReportsTestCase
{
    public function test_school_overview_counts_across_all_schools(): void
    {
        $this->newSchool(['status' => 'active']);
        $this->newSchool(['status' => 'active']);
        $this->newSchool(['status' => 'suspended']);

        $overview = app(PlatformReport::class)->schoolOverview();

        $this->assertSame(3, $overview['total_schools']);
        $this->assertSame(2, $overview['active_schools']);
        $this->assertSame(1, $overview['suspended_schools']);
    }

    public function test_module_adoption_reflects_overrides_and_defaults(): void
    {
        $enabledByDefaultModule = Module::Academics;
        $this->assertTrue($enabledByDefaultModule->enabledByDefault());

        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        // Explicitly disable an on-by-default module for one school only.
        $this->enterSchool($schoolA);
        SchoolModule::query()->create(['module' => $enabledByDefaultModule->value, 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $rows = app(PlatformReport::class)->moduleAdoption();
        $row = collect($rows)->firstWhere('module', $enabledByDefaultModule);

        $this->assertSame(2, $row['total_schools']);
        $this->assertSame(1, $row['enabled_schools']);
    }

    public function test_platform_usage_counts_students_and_teachers_across_every_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolA);
        Student::factory()->count(2)->create();
        Teacher::factory()->create();
        $this->app->forgetScopedInstances();

        $this->enterSchool($schoolB);
        Student::factory()->count(3)->create();
        $this->app->forgetScopedInstances();

        $usage = app(PlatformReport::class)->platformUsage();

        $this->assertSame(5, $usage['students']);
        $this->assertSame(1, $usage['teachers']);
    }
}
