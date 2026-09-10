<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Application user / authentication identity.
 *
 * A user may belong to several schools (`schools()`), holding a different Role
 * in each. The active school for a request is resolved by the `EnforceTenant`
 * middleware into `TenantContext`; permission checks (`hasPermission()`) read
 * from there, so a permission only applies inside the school the user is
 * currently working in.
 *
 * Neither `status` nor `is_platform_admin` is mass-assignable — both are
 * administrative flags, not values a registering or self-editing user may set.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * Per-request memo of the resolved role for each school id.
     *
     * @var array<int, Role|null>
     */
    private array $roleCache = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Default attribute values for new instances.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => UserStatus::Active->value,
        'is_platform_admin' => false,
    ];

    /**
     * Schools this user is a member of. The pivot carries the per-school `role`.
     *
     * @return BelongsToMany<School, $this>
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->using(SchoolUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Whether this account is permitted to authenticate. Checked server-side at
     * login and on every authenticated request — never in the UI alone.
     */
    public function canAuthenticate(): bool
    {
        return $this->status->allowsAuthentication();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Platform owner. A capability that spans the whole platform, entirely
     * separate from any per-school role. Platform admins still act on school
     * data through a selected tenant context, not around it — but within a
     * school they have entered they hold every school permission.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function belongsToSchool(School|int $school): bool
    {
        $schoolId = $school instanceof School ? $school->getKey() : $school;

        if ($this->relationLoaded('schools')) {
            return $this->schools->contains('id', $schoolId);
        }

        return $this->schools()->whereKey($schoolId)->exists();
    }

    /**
     * Whether this user may establish a tenant context for the given school:
     * a member, or any platform admin.
     */
    public function canAccessSchool(School $school): bool
    {
        return $this->isPlatformAdmin() || $this->belongsToSchool($school);
    }

    // -------------------------------------------------------------------------
    // Roles & permissions — always scoped to a school (the active tenant by
    // default). See docs/authorization.md.
    // -------------------------------------------------------------------------

    /**
     * The user's role in the given school (or the active tenant if omitted).
     * `null` if they are not a member, or a member without a role.
     */
    public function roleIn(School|int|null $school = null): ?Role
    {
        $schoolId = $this->resolveSchoolId($school);

        if ($schoolId === null) {
            return null;
        }

        if (! array_key_exists($schoolId, $this->roleCache)) {
            $value = $this->schools()->newPivotStatementForId($schoolId)->value('role');
            $this->roleCache[$schoolId] = $value !== null ? Role::tryFrom($value) : null;
        }

        return $this->roleCache[$schoolId];
    }

    /**
     * Every permission the user effectively holds in the given school.
     *
     * @return list<Permission>
     */
    public function permissionsIn(School|int|null $school = null): array
    {
        $schoolId = $this->resolveSchoolId($school);

        if ($schoolId === null) {
            return [];
        }

        if ($this->isPlatformAdmin()) {
            return Permission::all();
        }

        return $this->roleIn($schoolId)?->permissions() ?? [];
    }

    /**
     * Does the user hold $permission in the given school (default: active tenant)?
     *
     * Returns false — never throws — when there is no school in play, so it is
     * safe to call from layouts / account-level pages.
     */
    public function hasPermission(Permission $permission, School|int|null $school = null): bool
    {
        $schoolId = $this->resolveSchoolId($school);

        if ($schoolId === null) {
            return false;
        }

        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->roleIn($schoolId)?->grants($permission) ?? false;
    }

    /**
     * May this user assign $role to someone in the given school? Guards against
     * privilege escalation: you can never grant a role of a higher tier than
     * your own. (Platform admins may grant any role in a school they have
     * entered.)
     */
    public function canGrantRole(Role $role, School|int|null $school = null): bool
    {
        $schoolId = $this->resolveSchoolId($school);

        if ($schoolId === null || ! $this->hasPermission(Permission::MemberAssignRole, $schoolId)) {
            return false;
        }

        if ($this->isPlatformAdmin()) {
            return true;
        }

        $ownRole = $this->roleIn($schoolId);

        return $ownRole !== null && $role->tier() <= $ownRole->tier();
    }

    public function joinSchool(School $school, ?Role $role = null): void
    {
        $this->schools()->syncWithoutDetaching([
            $school->getKey() => ['role' => $role?->value],
        ]);
        unset($this->roleCache[$school->getKey()]);
    }

    public function assignRoleInSchool(School $school, ?Role $role): void
    {
        $this->schools()->updateExistingPivot($school->getKey(), ['role' => $role?->value]);
        unset($this->roleCache[$school->getKey()]);
    }

    public function leaveSchool(School $school): void
    {
        $this->schools()->detach($school->getKey());
        unset($this->roleCache[$school->getKey()]);
    }

    private function resolveSchoolId(School|int|null $school): ?int
    {
        if ($school instanceof School) {
            return $school->getKey();
        }

        if (is_int($school)) {
            return $school;
        }

        return app(TenantContext::class)->id();
    }
}
