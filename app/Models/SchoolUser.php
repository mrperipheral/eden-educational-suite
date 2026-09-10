<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `school_user` membership row — a user's link to one school, carrying the
 * per-school {@see Role}.
 *
 * Used as the pivot for `User::schools()` / `School::users()` (so `$model->pivot`
 * is a typed `SchoolUser` with the `role` cast) and queried directly by the
 * Members module.
 */
class SchoolUser extends Pivot
{
    protected $table = 'school_user';

    public $incrementing = false;

    /**
     * `role` is the only meaningful attribute callers set. It is always written
     * through `User::joinSchool()` / `assignRoleInSchool()` with a typed `Role`
     * enum; the Members controller never mass-assigns request data here, and the
     * value is validated + policy-checked before it reaches those helpers.
     *
     * @var list<string>
     */
    protected $fillable = ['role'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function grantsPermission(Permission $permission): bool
    {
        return $this->role?->grants($permission) ?? false;
    }
}
