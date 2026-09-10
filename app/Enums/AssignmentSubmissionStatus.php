<?php

namespace App\Enums;

use App\Models\Assessment;
use App\Models\Assignment;

/**
 * Whether one student has turned in an {@see Assignment}
 * (see `docs/assessment-management.md`).
 *
 * A single controlled status — never a spread of boolean flags. The default is
 * `Pending` (not turned in *yet*), which is real information, not "unknown": an
 * assignment's completion roster is materialised when it is created, so every
 * eligible student starts as `Pending` and the teacher moves them on.
 *
 * This tracks *completion only*. Grading a submission is a separate concern —
 * create an {@see Assessment} for that.
 */
enum AssignmentSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Late = 'late';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Submitted => __('Submitted'),
            self::Late => __('Late'),
            self::Exempt => __('Exempt'),
        };
    }

    /** Whether the student has handed the work in (on time or late). */
    public function isTurnedIn(): bool
    {
        return $this === self::Submitted || $this === self::Late;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Submitted => 'success',
            self::Late => 'brand',
            self::Exempt => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
