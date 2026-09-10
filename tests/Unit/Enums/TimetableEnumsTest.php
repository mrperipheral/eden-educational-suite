<?php

namespace Tests\Unit\Enums;

use App\Enums\TimetableStatus;
use App\Enums\Weekday;
use Tests\TestCase;

class TimetableEnumsTest extends TestCase
{
    public function test_timetable_status_values_and_helpers(): void
    {
        $this->assertSame(['draft', 'published'], array_map(fn (TimetableStatus $s) => $s->value, TimetableStatus::all()));

        foreach (TimetableStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(TimetableStatus::Published->isPublished());
        $this->assertFalse(TimetableStatus::Draft->isPublished());
    }

    public function test_weekday_helpers_list_all_seven_days_monday_first(): void
    {
        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 0],
            array_map(fn (Weekday $d) => $d->value, Weekday::all()),
        );

        foreach (Weekday::cases() as $day) {
            $this->assertNotSame('', $day->label());
            $this->assertSame(3, mb_strlen($day->short()));
        }
    }
}
