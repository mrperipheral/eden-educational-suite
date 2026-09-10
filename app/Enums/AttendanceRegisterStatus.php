<?php

namespace App\Enums;

/**
 * An attendance register's lifecycle state (see `docs/attendance-management.md`).
 *
 * `Draft` — being filled in; the class teacher / admin marks students and may
 * change anything.
 * `Submitted` — locked. Normal users can no longer change the marks; a
 * correction requires an `attendance.manage` holder to `reopen()` it first, so
 * historical records are never silently rewritten.
 *
 * No approval workflow.
 */
enum AttendanceRegisterStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Submitted => __('Submitted'),
        };
    }

    public function isLocked(): bool
    {
        return $this === self::Submitted;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Submitted => 'success',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
