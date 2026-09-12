<?php

namespace App\Enums;

use App\Models\ExamAttempt;

/**
 * When a completed {@see ExamAttempt}'s score/percentage/
 * pass-fail becomes visible to the student who sat it (`docs/cbt.md` §3).
 * Never governs whether *correct answers* are exposed — that is never
 * automatic in M23 regardless of release mode (see `docs/cbt.md` §4).
 */
enum ResultReleaseMode: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => __('Immediately after submission'),
            self::Scheduled => __('Scheduled release'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
