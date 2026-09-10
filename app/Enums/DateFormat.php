<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How a school prefers dates to be displayed across the app and (later) its
 * portals. The value is a PHP `date()` format string so callers can format
 * directly: `$date->format($school->settings->date_format->value)`.
 *
 * Deliberately a short, closed set — this is a display preference, not a
 * free-form format builder.
 */
enum DateFormat: string
{
    case DayMonthYear = 'd/m/Y';
    case MonthDayYear = 'm/d/Y';
    case IsoDate = 'Y-m-d';
    case LongDate = 'j M Y';

    public function label(): string
    {
        return match ($this) {
            self::DayMonthYear => __('Day / Month / Year'),
            self::MonthDayYear => __('Month / Day / Year'),
            self::IsoDate => __('ISO (Year-Month-Day)'),
            self::LongDate => __('Long (e.g. 5 Jan 2026)'),
        };
    }

    public function format(CarbonInterface $date): string
    {
        return $date->format($this->value);
    }

    public function example(): string
    {
        return $this->format(now()->setDate(2026, 1, 5));
    }
}
