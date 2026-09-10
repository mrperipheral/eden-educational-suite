<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\SchoolUser;
use App\Models\User;

/**
 * Authorization for managing a school membership (`school_user` row).
 *
 * The coarse "can this user touch the Members area at all" check is the
 * `member.*` Gate abilities. This policy adds the per-row rules:
 *
 *   - you can never change or remove your own membership;
 *   - you can never grant a role more powerful than your own
 *     ({@see User::canGrantRole()}).
 *
 * All checks run against the caller's role **in the active tenant** — the
 * controller only ever loads `SchoolUser` rows for the current school, so a
 * cross-school membership can never reach here.
 */
class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::MemberView);
    }

    public function assignRole(User $user, SchoolUser $membership, Role $target): bool
    {
        if ($membership->user_id === $user->id) {
            return false;
        }

        return $user->canGrantRole($target, $membership->school_id);
    }

    public function remove(User $user, SchoolUser $membership): bool
    {
        if ($membership->user_id === $user->id) {
            return false;
        }

        return $user->hasPermission(Permission::MemberRemove, $membership->school_id);
    }
}
