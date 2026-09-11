<?php

namespace App\Enums;

use App\Models\Assessment;

/**
 * An assignment's lifecycle state (see `docs/assessment-management.md`).
 *
 * `Draft` — being prepared; not yet visible as set work. The owner edits freely.
 * `Published` — issued to the class. Completion tracking happens here.
 * `Closed` — the assignment is done with; completion records are frozen. A
 * manager or the owner can `reopen()` it to `Published` for a correction.
 *
 * An assignment is *not* an assessment — it carries no scores. It may optionally
 * be referenced by an {@see Assessment} that grades it, but M14 does
 * not calculate anything from that link.
 */
enum AssignmentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Closed => __('Closed'),
        };
    }

    /** Whether completion records may still be changed. */
    public function tracksCompletion(): bool
    {
        return $this !== self::Closed;
    }

    public function structureEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Whether an assignment in this state may be shown in the Parent Portal
     * (M16, `docs/parent-portal.md`) — issued or done work only; a `Draft`
     * assignment is not yet real set work and stays teacher/admin-only.
     */
    public function visibleToParents(): bool
    {
        return $this !== self::Draft;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'brand',
            self::Closed => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
