<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active school for the request and populates
 * {@see TenantContext}. Runs after `auth` / `verified` / `active`.
 *
 * Resolution order:
 *   1. A school id stored in the session by the school switcher — used only if
 *      it still exists, is active, and the user may access it.
 *   2. Auto-selected when a non-platform-admin user is a member of exactly one
 *      active school.
 *   3. Otherwise the user is sent to the school picker.
 *
 * The session only ever holds an id; access is re-checked from the database on
 * every request, so changing the stored id achieves nothing.
 */
class EnforceTenant
{
    public const SESSION_KEY = 'tenant.school_id';

    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $school = $this->resolveSchool($request, $user);

        if ($school === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('school-context.create');
        }

        $this->tenant->set($school);
        $request->session()->put(self::SESSION_KEY, $school->getKey());

        return $next($request);
    }

    private function resolveSchool(Request $request, User $user): ?School
    {
        $selectedId = $request->session()->get(self::SESSION_KEY);

        if ($selectedId !== null) {
            $school = School::query()->active()->whereKey($selectedId)->first();

            if ($school !== null && $user->canAccessSchool($school)) {
                return $school;
            }

            $request->session()->forget(self::SESSION_KEY);
        }

        if (! $user->isPlatformAdmin()) {
            $schools = $user->schools()->active()->limit(2)->get();

            if ($schools->count() === 1) {
                return $schools->first();
            }
        }

        return null;
    }
}
