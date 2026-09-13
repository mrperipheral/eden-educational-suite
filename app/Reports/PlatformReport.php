<?php

namespace App\Reports;

use App\Enums\Module;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform-level cross-school reporting for Super Admin / Platform Admin
 * only (M27 §13). **Not** a tenant bypass — this class exists precisely
 * because a platform overview is legitimately cross-school by design
 * (`Platform\ReportController` gates it via `SchoolPolicy::viewAny`
 * exactly like the existing `Platform\SchoolController`), and every query
 * here is either genuinely schoolless (`School`, `AuditLog`) or explicitly
 * wrapped in `TenantContext::runWithoutScope()` — the one sanctioned escape
 * hatch, never a silent/implicit bypass. Kept deliberately aggregated: no
 * per-school PII, only counts. See `docs/reporting.md` §13.
 */
class PlatformReport
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{total_schools:int, active_schools:int, suspended_schools:int, recent_schools: Collection}
     */
    public function schoolOverview(): array
    {
        // ->toBase(): status is enum-cast on the model, and an enum
        // instance can't be a PHP array key.
        $byStatus = School::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        return [
            'total_schools' => (int) $byStatus->sum(),
            'active_schools' => (int) ($byStatus['active'] ?? 0),
            'suspended_schools' => (int) ($byStatus['suspended'] ?? 0),
            'recent_schools' => School::query()->orderByDesc('created_at')->limit(10)->get(['id', 'name', 'slug', 'status', 'created_at']),
        ];
    }

    /**
     * How many schools currently have each catalogue module enabled —
     * derived from the override-only `school_modules` table plus each
     * module's own default, never by loading every school and re-deriving
     * per row in PHP.
     *
     * @return list<array{module: Module, enabled_schools:int, total_schools:int}>
     */
    public function moduleAdoption(): array
    {
        $totalSchools = School::query()->count();

        $overridesByModule = DB::table('school_modules')
            ->select('module', 'enabled')
            ->get()
            ->groupBy('module');

        $rows = [];

        foreach (Module::cases() as $module) {
            $overrides = $overridesByModule->get($module->value, collect());
            $enabledOverrides = $overrides->where('enabled', true)->count();
            $disabledOverrides = $overrides->where('enabled', false)->count();
            $withoutOverride = $totalSchools - $enabledOverrides - $disabledOverrides;

            $enabledCount = $module->enabledByDefault()
                ? $withoutOverride + $enabledOverrides
                : $enabledOverrides;

            $rows[] = [
                'module' => $module,
                'enabled_schools' => max(0, $enabledCount),
                'total_schools' => $totalSchools,
            ];
        }

        return $rows;
    }

    /**
     * @return array{users:int, students:int, teachers:int, recent_audit_activity: Collection}
     */
    public function platformUsage(): array
    {
        return [
            'users' => User::query()->count(),
            'students' => $this->tenant->runWithoutScope(fn () => Student::query()->count()),
            'teachers' => $this->tenant->runWithoutScope(fn () => Teacher::query()->count()),
            'recent_audit_activity' => AuditLog::query()
                ->whereNotNull('school_id')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }
}
