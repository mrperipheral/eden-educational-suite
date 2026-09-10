<?php

namespace App\Enums;

/**
 * A teacher's employment relationship with the school. Distinct from
 * {@see TeacherAssignmentStatus}, which describes one teaching assignment.
 *
 * Historical records are always retained — a teacher who leaves is marked
 * `Resigned`, never deleted (see `docs/teacher-management.md`). M11 is a
 * professional record, not an HR / payroll system.
 */
enum TeacherStatus: string
{
    /** Currently employed and working. */
    case Active = 'active';

    /** Employed but temporarily not working (leave of absence, sabbatical). */
    case Inactive = 'inactive';

    /** Employment on hold pending a disciplinary / review process. */
    case Suspended = 'suspended';

    /** Has left the school. */
    case Resigned = 'resigned';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Suspended => __('Suspended'),
            self::Resigned => __('Resigned'),
        };
    }

    /** Whether a teacher in this status is still on the school's books. */
    public function isEmployed(): bool
    {
        return $this !== self::Resigned;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Suspended => 'danger',
            self::Resigned => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
