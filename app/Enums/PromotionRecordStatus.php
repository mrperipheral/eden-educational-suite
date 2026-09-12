<?php

namespace App\Enums;

use App\Models\PromotionBatch;

/**
 * One student's outcome within a {@see PromotionBatch}
 * (Milestone 21, `docs/promotion.md`).
 *
 * `Promoted` — a new target enrollment was created; the source enrollment
 * was preserved, closed (`completed`) by `App\Models\Enrollment::
 * makeActive()`, exactly as an ordinary class change already works.
 * `Skipped` — an eligibility rule stopped it *before* anything was written
 * (e.g. already enrolled in the target session) — not an error, a safe no-op.
 * `Failed` — an unexpected condition stopped it (e.g. the student vanished
 * from the eligible roster between page load and submission).
 *
 * Exactly one record per (batch, student) — `unique(school_id,
 * promotion_batch_id, student_id)` — so a batch can never log the same
 * student's transition twice.
 */
enum PromotionRecordStatus: string
{
    case Promoted = 'promoted';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => __('Promoted'),
            self::Skipped => __('Skipped'),
            self::Failed => __('Failed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Promoted => 'success',
            self::Skipped => 'gray',
            self::Failed => 'danger',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
