<?php

namespace App\Models;

use App\Enums\UserStatus;
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
 * A user may belong to several schools (`schools()`); the active one for a
 * request is resolved by App\Http\Middleware\EnforceTenant into
 * App\Support\Tenancy\TenantContext.
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
     * Schools this user is a member of.
     *
     * @return BelongsToMany<School, $this>
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)->withTimestamps();
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
     * data through a selected tenant context, not around it.
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
}
