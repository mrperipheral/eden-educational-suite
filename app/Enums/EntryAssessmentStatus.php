<?php

namespace App\Enums;

use App\Models\EntryAssessment;

/**
 * An {@see EntryAssessment}'s own retention lifecycle (Milestone 25,
 * `docs/entry-placement-assessment.md`) — not mass-assignable, changed only
 * through `EntryAssessment::archive()` / `restore()`. Nothing ever attaches
 * to an entry/placement assessment record the way an examination attaches to
 * a Question Bank entry, so an archived record is never a concern for
 * historical integrity elsewhere — it is simply hidden from the "current"
 * working set while the row itself is never hard-deleted.
 *
 * This is deliberately **not** the assessment's own conducted/pending
 * outcome — whether a score has been entered is read directly from
 * `score === null`, and any pass/fail-style outcome the school records is
 * the free-text `result` field. This enum only ever answers "is this record
 * part of the active working set, or has it been retired?".
 */
enum EntryAssessmentStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Archived => __('Archived'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'gray',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
