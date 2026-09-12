<?php

namespace App\Enums;

/**
 * The M23-supported question types (`docs/cbt.md`). Both are objective
 * (single correct option, server-markable) — essay/manual-marking question
 * types are explicitly out of scope for this milestone.
 *
 * Both types are stored through the same `QuestionOption`/
 * `ExaminationQuestionOption` rows — `TrueFalse` is simply an MCQ
 * constrained to exactly two options — so scoring never special-cases the
 * type; it only governs the authoring form and option-count validation.
 */
enum ExaminationQuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => __('Multiple choice'),
            self::TrueFalse => __('True / False'),
        };
    }

    /** The exact number of options this type requires, or null for "two or more." */
    public function fixedOptionCount(): ?int
    {
        return match ($this) {
            self::TrueFalse => 2,
            self::MultipleChoice => null,
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
