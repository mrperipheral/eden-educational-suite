<?php

namespace App\Enums;

use App\Models\AssessmentScore;

/**
 * Where an {@see AssessmentScore} value came from (see
 * `docs/results-report-cards.md`).
 *
 * M15 v1 only ever writes `Manual` (a teacher/admin typing in a paper/offline
 * mark through the existing M14 score-entry screen). `OnlineCbt` and `Imported`
 * are declared now so a future CBT engine or bulk-import tool can write scores
 * into the same `assessment_scores` table without a schema change or a second
 * result pipeline — the result engine never needs to know which source a score
 * came from.
 */
enum ScoreSource: string
{
    case Manual = 'manual';
    case OnlineCbt = 'online_cbt';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::OnlineCbt => __('Online CBT'),
            self::Imported => __('Imported'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
