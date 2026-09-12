<?php

namespace App\Enums;

use App\Models\Question;

/**
 * A coarse difficulty rating for a {@see Question} (Milestone
 * 24, `docs/question-bank.md`) — a simple, fixed three-level scale, not a
 * school-configurable taxonomy (that would be over-engineering this
 * milestone's actual need: a useful list filter and a hint for whoever is
 * assembling an examination).
 */
enum QuestionDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => __('Easy'),
            self::Medium => __('Medium'),
            self::Hard => __('Hard'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Easy => 'success',
            self::Medium => 'warning',
            self::Hard => 'danger',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
