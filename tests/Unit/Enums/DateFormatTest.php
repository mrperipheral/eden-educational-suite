<?php

namespace Tests\Unit\Enums;

use App\Enums\DateFormat;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DateFormatTest extends TestCase
{
    public function test_value_is_a_usable_php_date_format(): void
    {
        $date = Carbon::create(2026, 1, 5, 0, 0, 0);

        $this->assertSame('05/01/2026', DateFormat::DayMonthYear->format($date));
        $this->assertSame('01/05/2026', DateFormat::MonthDayYear->format($date));
        $this->assertSame('2026-01-05', DateFormat::IsoDate->format($date));
        $this->assertSame('5 Jan 2026', DateFormat::LongDate->format($date));
    }

    public function test_example_uses_a_fixed_sample_date(): void
    {
        $this->assertSame('05/01/2026', DateFormat::DayMonthYear->example());
        $this->assertSame('2026-01-05', DateFormat::IsoDate->example());
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (DateFormat::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
