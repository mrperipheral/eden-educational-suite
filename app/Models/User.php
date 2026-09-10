<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Application user / authentication identity.
 *
 * Domain profiles (staff, guardian, student) and the user↔school relationship
 * are added in later milestones — this model stays intentionally thin.
 *
 * `status` is never mass-assignable: it is an administrative flag, not
 * something a registering or self-editing user may set.
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
        ];
    }

    /**
     * Default attribute values for new instances.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => UserStatus::Active->value,
    ];

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
}
