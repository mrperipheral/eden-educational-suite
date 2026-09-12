<?php

namespace App\Enums;

use App\Models\PromotionBatch;

/**
 * The aggregate outcome of a {@see PromotionBatch} (Milestone
 * 21, `docs/promotion.md`). Computed once, after every selected student has
 * been processed — there is no `pending`/`approved` intermediate state: the
 * authorised action of running the batch *is* the authorisation (gated
 * `promotion.manage`), so a separate approval step would just be workflow
 * ceremony the spec explicitly asked not to build.
 */
enum PromotionBatchStatus: string
{
    /** Every selected student was promoted. */
    case Completed = 'completed';

    /** Some students were promoted; at least one was skipped or failed. */
    case PartiallyCompleted = 'partially_completed';

    /** No student was promoted. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::PartiallyCompleted => __('Partially completed'),
            self::Failed => __('Failed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::PartiallyCompleted => 'warning',
            self::Failed => 'danger',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
