<?php

namespace App\Support\Tenancy;

use App\Models\School;
use App\Support\Tenancy\Exceptions\MissingTenantContextException;

/**
 * Central per-request tenant (school) context.
 *
 * The single seam through which the application answers "which school are we
 * acting for right now?". Everything tenant-aware reads from here:
 *
 *   - the `EnforceTenant` middleware resolves the school for the authenticated
 *     user / session and calls `set()`;
 *   - `SchoolScope` (a global scope on every `BelongsToSchool` model) reads
 *     `idOrFail()` to constrain queries;
 *   - the same trait's `creating` hook stamps `school_id` from `id()`.
 *
 * Application code must never read a raw `school_id` from the request. It asks
 * this object, so isolation is a property of the framework wiring rather than of
 * every developer remembering a `where()` clause.
 *
 * Registered as `scoped()` (one instance per request) in AppServiceProvider.
 */
class TenantContext
{
    private ?School $school = null;

    private ?int $schoolId = null;

    private bool $bypassed = false;

    /**
     * Establish the active tenant from a loaded School model (the common path).
     */
    public function set(School $school): void
    {
        $this->school = $school;
        $this->schoolId = $school->getKey();
    }

    /**
     * Establish the active tenant from an id alone — for queue jobs / console
     * routines that carry an id but have no need for the model.
     */
    public function setId(int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->school = null;
    }

    public function forget(): void
    {
        $this->school = null;
        $this->schoolId = null;
    }

    public function has(): bool
    {
        return $this->schoolId !== null;
    }

    public function id(): ?int
    {
        return $this->schoolId;
    }

    /**
     * The current school id, or fail loudly. Used by the global scope so a
     * missing context is a bug, never silent cross-tenant exposure.
     */
    public function idOrFail(): int
    {
        if ($this->schoolId === null) {
            throw MissingTenantContextException::make();
        }

        return $this->schoolId;
    }

    /**
     * The current School model. Lazily loaded (and cached) if only an id was set.
     */
    public function school(): ?School
    {
        if ($this->school === null && $this->schoolId !== null) {
            $this->school = School::find($this->schoolId);
        }

        return $this->school;
    }

    public function schoolOrFail(): School
    {
        $school = $this->school();

        if ($school === null) {
            throw MissingTenantContextException::make();
        }

        return $school;
    }

    /**
     * Whether tenant scoping is currently bypassed (see {@see self::runWithoutScope()}).
     * The global scope checks this before constraining queries.
     */
    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Escape hatch for genuinely cross-tenant work — platform-admin reports,
     * scheduled maintenance, migrations. Keep the callback as small as possible;
     * anything inside it can read and write every school's data.
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public function runWithoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }
}
