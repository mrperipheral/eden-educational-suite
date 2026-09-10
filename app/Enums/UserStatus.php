<?php

namespace App\Enums;

/**
 * Account status.
 *
 * Deliberately small — this is not an account-lifecycle state machine. Only
 * {@see self::Active} accounts may authenticate; every other value blocks
 * authentication server-side (see App\Http\Requests\Auth\LoginRequest and
 * App\Http\Middleware\EnsureAccountIsActive).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';

    /** Whether an account in this status is allowed to authenticate. */
    public function allowsAuthentication(): bool
    {
        return $this === self::Active;
    }

    /** Human-readable label for UI. */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Message shown to a user whose account cannot sign in. Intentionally generic. */
    public function authenticationBlockedMessage(): string
    {
        return match ($this) {
            self::Suspended => 'This account has been suspended. Please contact your administrator.',
            self::Disabled => 'This account is no longer active. Please contact your administrator.',
            self::Active => '',
        };
    }
}
