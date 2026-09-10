<?php

namespace App\Enums;

/**
 * Day of the week, using Carbon's numbering (0 = Sunday … 6 = Saturday) so it
 * drops straight into `Carbon::startOfWeek()` / calendar maths that later
 * attendance and timetable modules will need.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sunday => __('Sunday'),
            self::Monday => __('Monday'),
            self::Tuesday => __('Tuesday'),
            self::Wednesday => __('Wednesday'),
            self::Thursday => __('Thursday'),
            self::Friday => __('Friday'),
            self::Saturday => __('Saturday'),
        };
    }
}
