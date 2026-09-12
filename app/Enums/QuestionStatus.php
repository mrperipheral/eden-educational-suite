<?php

namespace App\Enums;

use App\Models\ExamAttempt;
use App\Models\ExaminationQuestion;
use App\Models\Question;

/**
 * A {@see Question}'s own lifecycle (Milestone 24,
 * `docs/question-bank.md`) — separate from any {@see
 * \App\Models\Examination}/{@see ExamAttempt} lifecycle. Not
 * mass-assignable — changed only through `Question::activate()` /
 * `deactivate()` / `archive()`.
 *
 * Only `Active` questions may be **newly** attached to an examination
 * ({@see self::isSelectable()}); an already-attached
 * {@see ExaminationQuestion} snapshot is completely
 * unaffected by its source question's status changing later — see
 * `docs/cbt.md` §6.
 */
enum QuestionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Archived => __('Archived'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Archived => 'gray',
        };
    }

    /** Whether a question in this status may be newly attached to an examination. */
    public function isSelectable(): bool
    {
        return $this === self::Active;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
