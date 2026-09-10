<?php

namespace App\Enums;

/**
 * The state of one `App\Models\Enrollment` — a student's placement in a
 * level/arm for a session. A student has at most one `Active` enrollment (their
 * current class); the rest are history.
 *
 * Distinct from {@see StudentStatus}, which is the student's relationship with
 * the school overall.
 */
enum EnrollmentStatus: string
{
    /** The student's current placement. */
    case Active = 'active';

    /** The placement ran its course (session ended, student moved on). */
    case Completed = 'completed';

    /** The placement ended early (student left / was removed mid-way). */
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Completed => __('Completed'),
            self::Withdrawn => __('Withdrawn'),
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Active;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'gray',
            self::Withdrawn => 'warning',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
