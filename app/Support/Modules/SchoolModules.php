<?php

namespace App\Support\Modules;

use App\Enums\Module;
use App\Models\SchoolModule;
use App\Support\Tenancy\TenantContext;

/**
 * Answers "is this feature module on for the school we are acting for right
 * now?" — the single reusable seam every future domain module checks.
 *
 * Registered as `scoped()` (one instance per request) in `AppServiceProvider`,
 * so the school's stored overrides are read from the database **once per
 * request** and every subsequent `enabled()` / `states()` call is served from
 * memory. The cache is keyed by the active school id and reloaded if the tenant
 * context changes under it, so the resolver is correct even if the instance
 * outlives a single context (console, tests, Octane).
 *
 * Reads are tenant-scoped automatically: `SchoolModule` is a `BelongsToSchool`
 * model, so this only ever sees the active school's rows and fails closed
 * (`MissingTenantContextException`) when there is no tenant context.
 *
 * Module activation is configuration, not authorization — nothing here touches
 * permissions or the Gate. See `docs/module-activation.md`.
 */
class SchoolModules
{
    /**
     * The active school's explicit overrides: module value => enabled.
     *
     * @var array<string, bool>
     */
    private array $overrides = [];

    /** The school id `$overrides` was loaded for, or null if not yet loaded. */
    private ?int $loadedForSchool = null;

    public function enabled(Module $module): bool
    {
        return $this->overrides()[$module->value] ?? $module->enabledByDefault();
    }

    /**
     * The resolved on/off state of every catalogue module for the active school
     * (stored override where present, otherwise the catalogue default).
     *
     * @return array<string, bool> module value => enabled
     */
    public function states(): array
    {
        $overrides = $this->overrides();

        $states = [];

        foreach (Module::cases() as $module) {
            $states[$module->value] = $overrides[$module->value] ?? $module->enabledByDefault();
        }

        return $states;
    }

    /**
     * Persist an explicit on/off preference for the active school, upserting the
     * single `school_modules` row for this module.
     */
    public function set(Module $module, bool $enabled): void
    {
        $this->overrides();

        $row = SchoolModule::query()->firstOrNew(['module' => $module->value]);
        $row->enabled = $enabled;
        $row->save();

        $this->overrides[$module->value] = $enabled;
    }

    /**
     * @return array<string, bool>
     */
    private function overrides(): array
    {
        // Resolved fresh (not constructor-injected) so a resolver instance that
        // outlives one request — tests, console, Octane — still reads the
        // *current* tenant rather than a captured one.
        $schoolId = app(TenantContext::class)->idOrFail();

        if ($this->loadedForSchool !== $schoolId) {
            $this->overrides = [];

            foreach (SchoolModule::query()->get(['module', 'enabled']) as $row) {
                // Ignore identifiers the catalogue no longer defines — a stale
                // row must never break a page. (Fail safe to defaults.)
                if (Module::tryFrom($row->module) !== null) {
                    $this->overrides[$row->module] = (bool) $row->enabled;
                }
            }

            $this->loadedForSchool = $schoolId;
        }

        return $this->overrides;
    }
}
