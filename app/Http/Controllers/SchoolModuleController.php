<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Http\Requests\Settings\UpdateSchoolModuleRequest;
use App\Support\Modules\SchoolModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The active school's feature/module activation (see `docs/module-activation.md`).
 *
 * Tenant-scoped and gated exactly like the rest of school settings:
 * `school.settings.view` reads the catalogue page, `school.settings.update`
 * toggles a module. State is school-owned via `SchoolModule` / `SchoolModules`,
 * so another school's configuration is unreachable here and `school_id` is never
 * read from input.
 *
 * Activation is configuration only — turning a module on grants nobody any
 * permission (M4 authorization stays authoritative).
 */
class SchoolModuleController extends Controller
{
    public function __construct(private readonly SchoolModules $modules) {}

    public function edit(): View
    {
        $this->authorize('school.settings.view');

        $states = $this->modules->states();

        $groups = [];

        foreach (Module::grouped() as $groupLabel => $cases) {
            foreach ($cases as $module) {
                $groups[$groupLabel][] = [
                    'module' => $module,
                    'enabled' => $states[$module->value],
                    'available' => $module->isAvailable(),
                    'dependencies' => $module->dependencies(),
                ];
            }
        }

        return view('settings.school.modules', ['groups' => $groups]);
    }

    public function update(UpdateSchoolModuleRequest $request, string $module): RedirectResponse
    {
        $target = Module::tryFrom($module);

        abort_if($target === null, 404);

        $enabled = $request->enabled();
        $states = $this->modules->states();

        if ($enabled) {
            $this->assertDependenciesEnabled($target, $states);
        } else {
            $this->assertNoEnabledDependents($target, $states);
        }

        $this->modules->set($target, $enabled);

        return to_route('settings.school.modules.edit')->with('status', $enabled
            ? __(':module enabled.', ['module' => $target->label()])
            : __(':module disabled.', ['module' => $target->label()]));
    }

    /**
     * @param  array<string, bool>  $states
     */
    private function assertDependenciesEnabled(Module $target, array $states): void
    {
        $missing = array_values(array_filter(
            $target->dependencies(),
            fn (Module $dependency) => ! ($states[$dependency->value] ?? false),
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'module' => __(':target needs :modules — enable :modules first.', [
                    'modules' => $this->names($missing),
                    'target' => $target->label(),
                ]),
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $states
     */
    private function assertNoEnabledDependents(Module $target, array $states): void
    {
        $dependents = array_values(array_filter(
            Module::cases(),
            fn (Module $module) => ($states[$module->value] ?? false)
                && in_array($target, $module->dependencies(), true),
        ));

        if ($dependents !== []) {
            throw ValidationException::withMessages([
                'module' => __(':modules depend on :target — disable :modules first.', [
                    'modules' => $this->names($dependents),
                    'target' => $target->label(),
                ]),
            ]);
        }
    }

    /**
     * @param  list<Module>  $modules
     */
    private function names(array $modules): string
    {
        return implode(', ', array_map(fn (Module $module) => $module->label(), $modules));
    }
}
