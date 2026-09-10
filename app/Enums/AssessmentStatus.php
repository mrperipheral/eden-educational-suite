<?php

namespace App\Enums;

/**
 * An assessment's lifecycle state (see `docs/assessment-management.md`).
 *
 * `Draft` — being configured. The recording teacher / admin can edit the
 * structure (title, category, max score, instructions) and enter or change
 * scores freely.
 * `Published` — the structure is frozen; normal score entry continues. Use this
 * once the assessment definition is settled so scores are entered against a
 * stable maximum.
 * `Locked` — finalized. Neither the structure nor the scores can be changed by
 * ordinary users. A correction requires an `assessment.manage` holder to
 * `unlock()` it first, so finalized historical data is never silently rewritten.
 *
 * No approval workflow — M14 stores source assessment data; M15 compiles results.
 */
enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Locked => __('Locked'),
        };
    }

    /** Whether scores may still be entered / changed by an authorized recorder. */
    public function acceptsScores(): bool
    {
        return $this !== self::Locked;
    }

    /** Whether the assessment structure (title, max score, …) may still change. */
    public function structureEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isLocked(): bool
    {
        return $this === self::Locked;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Published => 'brand',
            self::Locked => 'success',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
