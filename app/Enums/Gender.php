<?php

namespace App\Enums;

/**
 * A small, controlled set for the student `gender` field. The field itself is
 * optional — a school records it only if it needs to (class registers, sports,
 * uniform). Nothing else is collected on identity grounds.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => __('Male'),
            self::Female => __('Female'),
            self::Other => __('Other'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
