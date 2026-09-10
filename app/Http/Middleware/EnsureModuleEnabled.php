<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Support\Modules\SchoolModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for feature modules: `->middleware('module:attendance')`.
 *
 * Runs after `tenant` (it needs an active school). If the named module is
 * disabled for the current school the route behaves as if it does not exist
 * (404) — the feature simply is not part of that school's application.
 *
 * This is the reusable seam every future domain module uses. It checks *only*
 * whether the module is switched on; it grants nothing. Routes still carry their
 * own `->can('...')` permission checks — the two are orthogonal.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly SchoolModules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $target = Module::tryFrom($module);

        if ($target === null) {
            throw new \InvalidArgumentException(
                "Unknown module [{$module}] passed to the `module` middleware."
            );
        }

        abort_unless($this->modules->enabled($target), 404);

        return $next($request);
    }
}
