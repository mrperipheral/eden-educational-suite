<?php

namespace App\Enums;

use App\Models\ResultAdjustment;

/**
 * A result run's lifecycle state (see `docs/results-report-cards.md`).
 *
 * `Draft` — being configured (grading/weighting scheme, ranking toggle); not
 * yet compiled.
 * `Compiled` — the compiler has computed every student/subject result from
 * locked M14 assessment scores. Recompiling is allowed (`draft`/`compiled` /
 * `reviewed` only) so corrections upstream (more scores entered, more
 * assessments locked) can be pulled in before anything is finalized.
 * `Reviewed` — School Admin / Principal have looked the compiled numbers over
 * and may add class-teacher / principal comments. Still recompilable.
 * `Approved` — the formal sign-off point. From here on the run is **frozen**;
 * the only way to change a number is the explicit {@see ResultAdjustment}
 * workflow on one subject result at a time.
 * `Published` — report cards are visible / generatable to viewers (Teacher,
 * Staff). Still requires the adjustment workflow for any correction.
 * `Locked` — the historical, reproducible state. No bulk "unlock" exists by
 * design — only the adjustment workflow can touch a locked result, and even
 * that recomputes only the affected student's totals/position.
 */
enum ResultRunStatus: string
{
    case Draft = 'draft';
    case Compiled = 'compiled';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Published = 'published';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Compiled => __('Compiled'),
            self::Reviewed => __('Reviewed'),
            self::Approved => __('Approved'),
            self::Published => __('Published'),
            self::Locked => __('Locked'),
        };
    }

    /** Whether `compile()` may still (re)run — before the formal sign-off. */
    public function recompilable(): bool
    {
        return in_array($this, [self::Draft, self::Compiled, self::Reviewed], true);
    }

    /** Whether a number may only be changed through the adjustment workflow. */
    public function requiresAdjustment(): bool
    {
        return in_array($this, [self::Approved, self::Published, self::Locked], true);
    }

    public function isLocked(): bool
    {
        return $this === self::Locked;
    }

    /**
     * Whether a run in this state may be shown in the Parent Portal (M16,
     * `docs/parent-portal.md`). M15 has no dedicated "parent-visible" flag,
     * so this is the documented, safest interpretation: publication status
     * alone gates parent visibility — a `draft`/`compiled`/`reviewed`/
     * `approved` run is still subject to change and stays admin/staff-only.
     */
    public function visibleToParents(): bool
    {
        return in_array($this, [self::Published, self::Locked], true);
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Compiled, self::Reviewed => 'brand',
            self::Approved, self::Published => 'success',
            self::Locked => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
