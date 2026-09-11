<?php

namespace App\Enums;

use App\Models\CommunicationThread;

/**
 * How a {@see CommunicationThread} is routed/filtered in the
 * Communication Hub (M18, `docs/communication.md`). Coarse on purpose — a
 * later milestone may split a category further.
 */
enum CommunicationCategory: string
{
    case General = 'general';
    case Academic = 'academic';
    case Attendance = 'attendance';
    case Behavior = 'behavior';
    case Fees = 'fees';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::Academic => __('Academic'),
            self::Attendance => __('Attendance'),
            self::Behavior => __('Behavior'),
            self::Fees => __('Fees'),
            self::Other => __('Other'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
