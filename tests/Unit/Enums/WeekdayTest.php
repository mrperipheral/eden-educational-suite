<?php

namespace Tests\Unit\Enums;

use App\Enums\Weekday;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeekdayTest extends TestCase
{
    public function test_uses_carbon_day_of_week_numbering(): void
    {
        $this->assertSame(0, Weekday::Sunday->value);
        $this->assertSame(1, Weekday::Monday->value);
        $this->assertSame(6, Weekday::Saturday->value);

        // A known Sunday.
        $this->assertSame(Weekday::Sunday->value, Carbon::create(2026, 1, 4)->dayOfWeek);
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (Weekday::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
