<?php

namespace App\Enums;

/**
 * A student's overall relationship with the school. Distinct from
 * {@see EnrollmentStatus}, which describes one academic placement.
 *
 * Historical records are always retained — a student who leaves is marked
 * `Withdrawn` or `Graduated`, never deleted (see `docs/student-management.md`).
 */
enum StudentStatus: string
{
    /** Currently attending. */
    case Active = 'active';

    /** Temporarily not attending (leave of absence, suspension, deferral). */
    case Inactive = 'inactive';

    /** Has left the school before completing. */
    case Withdrawn = 'withdrawn';

    /** Has completed the school's programme. */
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Withdrawn => __('Withdrawn'),
            self::Graduated => __('Graduated'),
        };
    }

    /** Whether a student in this status is currently on the school's roll. */
    public function isEnrolled(): bool
    {
        return $this === self::Active || $this === self::Inactive;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Withdrawn => 'gray',
            self::Graduated => 'brand',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
