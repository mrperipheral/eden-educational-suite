<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\AssignMemberRoleRequest;
use App\Models\SchoolUser;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manage who belongs to the current school and what role they hold.
 *
 * Tenant-scoped: `school_user` is not a `BelongsToSchool` model, so every query
 * here is explicitly constrained to `TenantContext::idOrFail()`. A member of
 * another school can never be listed or modified through these routes.
 */
class MemberController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SchoolUser::class);

        $roleFilter = Role::tryFrom((string) $request->query('role'));

        $query = $this->tenant->schoolOrFail()->users()->orderBy('users.name');

        if ($roleFilter !== null) {
            $query->wherePivot('role', $roleFilter->value);
        }

        return view('members.index', [
            'members' => $query->paginate(25)->withQueryString(),
            'roleFilter' => $roleFilter,
            'assignableRoles' => $this->assignableRoles($request->user()),
            'canAssign' => $request->user()->hasPermission(Permission::MemberAssignRole),
            'canRemove' => $request->user()->hasPermission(Permission::MemberRemove),
        ]);
    }

    public function updateRole(AssignMemberRoleRequest $request, User $user): RedirectResponse
    {
        $membership = $this->membershipOrFail($user);
        $role = $request->role();

        $this->authorize('assignRole', [$membership, $role]);

        $user->assignRoleInSchool($this->tenant->schoolOrFail(), $role);

        return back()->with('status', __(':name is now :role.', [
            'name' => $user->name,
            'role' => $role->label(),
        ]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $membership = $this->membershipOrFail($user);

        $this->authorize('remove', $membership);

        $user->leaveSchool($this->tenant->schoolOrFail());

        return back()->with('status', __(':name has been removed from this school.', ['name' => $user->name]));
    }

    private function membershipOrFail(User $user): SchoolUser
    {
        return SchoolUser::query()
            ->where('school_id', $this->tenant->idOrFail())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    /**
     * Roles the acting user is allowed to hand out — shown in the UI; the
     * server re-checks via the policy regardless.
     *
     * @return list<Role>
     */
    private function assignableRoles(User $actor): array
    {
        return array_values(array_filter(
            Role::all(),
            fn (Role $role) => $actor->canGrantRole($role),
        ));
    }
}
