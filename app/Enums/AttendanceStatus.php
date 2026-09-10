<?php

namespace App\Enums;

/**
 * How one student was marked in an attendance register (see
 * `docs/attendance-management.md`).
 *
 * A single controlled status — never a spread of `is_present` / `is_absent`
 * boolean columns. A record whose status is **null** simply has not been marked
 * yet; a register cannot be submitted while any record is unmarked, so an
 * unmarked student is never silently counted as present.
 *
 * There is no minutes-late field — `Late` plus the optional free-text `note`
 * covers a daily register; per-lesson tardiness metrics are deferred.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Absent => __('Absent'),
            self::Late => __('Late'),
            self::Excused => __('Excused'),
        };
    }

    /** Whether the student was physically in class (present or late). */
    public function isAttending(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Excused => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
