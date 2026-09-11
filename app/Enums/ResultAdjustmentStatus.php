<?php

namespace App\Enums;

use App\Models\ResultAdjustment;

/**
 * The state of one {@see ResultAdjustment} (see
 * `docs/results-report-cards.md`).
 *
 * An adjustment is **proposed** (`Pending`) with the original value, the
 * requested value and a reason, then a holder of `result.adjust` explicitly
 * **applies** it (`Applied` — the value changes, the run's ranking is
 * recomputed) or **rejects** it (`Rejected` — nothing changes). This is not a
 * general-purpose edit: a `Pending` adjustment has no effect until applied.
 */
enum ResultAdjustmentStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Applied => __('Applied'),
            self::Rejected => __('Rejected'),
        };
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Applied => 'success',
            self::Rejected => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
