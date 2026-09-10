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

    /** Three-letter abbreviation, for compact grid headers. */
    public function short(): string
    {
        return match ($this) {
            self::Sunday => __('Sun'),
            self::Monday => __('Mon'),
            self::Tuesday => __('Tue'),
            self::Wednesday => __('Wed'),
            self::Thursday => __('Thu'),
            self::Friday => __('Fri'),
            self::Saturday => __('Sat'),
        };
    }

    /**
     * All seven days, Monday-first (the ISO / common school-week order). The
     * timetable never assumes *which* days a school teaches on — this is only
     * the order options are listed in.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [
            self::Monday, self::Tuesday, self::Wednesday, self::Thursday,
            self::Friday, self::Saturday, self::Sunday,
        ];
    }
}
