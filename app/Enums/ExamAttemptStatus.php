<?php

namespace App\Enums;

use App\Models\ExamAttempt;

/**
 * A student's own attempt lifecycle (Milestone 23, `docs/cbt.md`) —
 * deliberately separate from the exam's own {@see ExaminationStatus}.
 * "Not started" is never a stored state: no {@see ExamAttempt}
 * row exists at all until the student actually starts, so only two states
 * are ever persisted.
 */
enum ExamAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
