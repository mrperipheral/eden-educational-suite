<?php

namespace App\Enums;

/**
 * Lifecycle status of a school (tenant).
 *
 * Deliberately small. Only an {@see self::Active} school can be entered as a
 * tenant context; a suspended school's data stays intact but its users cannot
 * act within it. A full school-lifecycle / billing model belongs to a later
 * milestone.
 */
enum SchoolStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /** Whether a tenant context may be established for a school in this status. */
    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
