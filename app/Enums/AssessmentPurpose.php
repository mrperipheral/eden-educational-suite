<?php

namespace App\Enums;

use App\Models\Assessment;

/**
 * What an {@see Assessment} is *for* (see `docs/results-report-cards.md`).
 *
 * M14 only ever creates `Academic` assessments — this exists so M15's result
 * compilation can safely ignore assessments that are not real termly academic
 * work, without M14 having to build that functionality yet:
 *
 *   - `Academic` — ordinary continuous-assessment / exam work for a class,
 *     eligible for M15 result compilation. The default for every M14 assessment.
 *   - `Practice` — reserved for a future ungraded/practice assessment (e.g. a
 *     mock CBT). Never enters a result run.
 *   - `EntryPlacement` — reserved for a future entrance/placement assessment
 *     (e.g. admissions testing). Never enters a result run — it is not tied to
 *     a student's *current* class the way a termly result is.
 *
 * Only `Academic` is reachable through the current UI; the other cases exist so
 * a future milestone can introduce them without an M14/M15 schema change.
 */
enum AssessmentPurpose: string
{
    case Academic = 'academic';
    case Practice = 'practice';
    case EntryPlacement = 'entry_placement';

    public function label(): string
    {
        return match ($this) {
            self::Academic => __('Academic'),
            self::Practice => __('Practice'),
            self::EntryPlacement => __('Entry / placement'),
        };
    }

    /** Whether an assessment of this purpose may ever contribute to a result run. */
    public function countsTowardResults(): bool
    {
        return $this === self::Academic;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
