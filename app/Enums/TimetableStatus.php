<?php

namespace App\Enums;

/**
 * A timetable's lifecycle state (see `docs/timetable-management.md`).
 *
 * `Draft` — being built; may contain gaps, may be edited freely.
 * `Published` — the school's live schedule. Publishing is refused while the
 * timetable has any scheduling conflict, so a published timetable is always
 * internally consistent.
 *
 * No approval workflow — a manager flips between the two states directly.
 */
enum TimetableStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'success',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
