<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * Authorization for the school (tenant) resource itself.
 *
 * Platform-level actions (listing every school, creating / editing / deleting a
 * school) are restricted to platform admins. Access to *operate inside* a school
 * ({@see self::enter()}) is granted to members and platform admins alike — and
 * from there, school data is reached through the tenant context, not this policy.
 *
 * There is deliberately no `Gate::before()` blanket-allow for platform admins:
 * they act on school data within a chosen context like anyone else.
 */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, School $school): bool
    {
        return $user->canAccessSchool($school);
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, School $school): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, School $school): bool
    {
        return $user->isPlatformAdmin();
    }

    /**
     * May the user establish a tenant context for this school right now?
     */
    public function enter(User $user, School $school): bool
    {
        return $school->isActive() && $user->canAccessSchool($school);
    }
}
