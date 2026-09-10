<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Central per-request tenant (school) context.
 *
 * This is the single seam through which the application answers the question
 * "which school are we acting for right now?". Later milestones build tenant
 * isolation on top of it:
 *
 *   - middleware resolves the current school from the authenticated user /
 *     route and calls {@see self::set()};
 *   - a `BelongsToSchool` model trait applies a global scope that reads
 *     {@see self::id()} so queries are automatically constrained;
 *   - a model `creating` hook stamps `school_id` from {@see self::id()}.
 *
 * Application code must never read a raw `school_id` from the request. It asks
 * this object instead, so isolation cannot be forgotten in one controller.
 *
 * Registered as a singleton in AppServiceProvider; resolve via the container
 * or the `App\Support\Tenancy\Tenant` facade-style helper, not `new`.
 */
class TenantContext
{
    private ?int $schoolId = null;

    private bool $bypassed = false;

    public function set(int $schoolId): void
    {
        $this->schoolId = $schoolId;
    }

    public function forget(): void
    {
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
     * Return the current school id or fail loudly. Use this in code paths that
     * must be tenant-scoped so a missing context is a bug, not silent data
     * leakage.
     */
    public function idOrFail(): int
    {
        if ($this->schoolId === null) {
            throw new RuntimeException('No tenant (school) context has been set for this request.');
        }

        return $this->schoolId;
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
     * Escape hatch for genuinely cross-tenant work (platform admin reports,
     * scheduled jobs, migrations). Keep the callback as small as possible.
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
