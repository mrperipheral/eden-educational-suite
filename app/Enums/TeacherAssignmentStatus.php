<?php

namespace App\Enums;

/**
 * The state of one `App\Models\TeacherAssignment` — a teacher assigned to teach a
 * subject to a level/arm for a session. History is preserved: an assignment that
 * ends is marked `Ended`, never deleted.
 *
 * Distinct from `App\Enums\TeacherStatus`, which is the teacher's employment overall.
 */
enum TeacherAssignmentStatus: string
{
    /** The teacher currently holds this assignment. */
    case Active = 'active';

    /** The assignment has finished (session ended, reassigned, teacher left). */
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Ended => __('Ended'),
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
            self::Ended => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
