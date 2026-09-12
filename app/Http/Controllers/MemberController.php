<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\AssignMemberRoleRequest;
use App\Models\SchoolUser;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
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
 *
 * Every write here is a M26 audit event (`docs/audit.md`) — membership
 * created/removed and role changes are exactly the "user/access
 * administration" accountability the audit trail exists for.
 */
class MemberController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly AuditRecorder $audit) {}

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

    public function create(Request $request): View
    {
        $this->authorize('add', SchoolUser::class);

        return view('members.create', [
            'assignableRoles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(AddMemberRequest $request): RedirectResponse
    {
        $this->authorize('add', SchoolUser::class);

        $target = $request->targetUser();
        $role = $request->role();

        // No privilege escalation: the same tier check the Members list uses.
        abort_unless($request->user()->canGrantRole($role), 403);

        $target->joinSchool($this->tenant->schoolOrFail(), $role);

        $this->audit->record(
            event: 'member.created',
            summary: __(':actor added :name as :role.', ['actor' => $request->user()->name, 'name' => $target->name, 'role' => $role->label()]),
            auditable: $target,
            auditableLabel: $target->name,
            after: ['role' => $role->value],
        );

        return to_route('members.index')
            ->with('status', __(':name has been added as :role.', [
                'name' => $target->name,
                'role' => $role->label(),
            ]));
    }

    public function updateRole(AssignMemberRoleRequest $request, User $user): RedirectResponse
    {
        $membership = $this->membershipOrFail($user);
        $role = $request->role();
        $previousRole = $membership->role;

        $this->authorize('assignRole', [$membership, $role]);

        $user->assignRoleInSchool($this->tenant->schoolOrFail(), $role);

        $this->audit->record(
            event: 'member.role_changed',
            summary: __(':actor changed :name\'s role from :from to :to.', [
                'actor' => $request->user()->name, 'name' => $user->name,
                'from' => $previousRole?->label() ?? __('none'), 'to' => $role->label(),
            ]),
            auditable: $user,
            auditableLabel: $user->name,
            before: ['role' => $previousRole?->value],
            after: ['role' => $role->value],
        );

        return back()->with('status', __(':name is now :role.', [
            'name' => $user->name,
            'role' => $role->label(),
        ]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $membership = $this->membershipOrFail($user);

        $this->authorize('remove', $membership);

        $previousRole = $membership->role;
        $user->leaveSchool($this->tenant->schoolOrFail());

        $this->audit->record(
            event: 'member.removed',
            summary: __(':actor removed :name from this school.', ['actor' => $request->user()->name, 'name' => $user->name]),
            auditable: $user,
            auditableLabel: $user->name,
            before: ['role' => $previousRole?->value],
        );

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
