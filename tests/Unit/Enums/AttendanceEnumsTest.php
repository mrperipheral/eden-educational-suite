<?php

namespace Tests\Unit\Enums;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceStatus;
use Tests\TestCase;

class AttendanceEnumsTest extends TestCase
{
    public function test_attendance_status_values_and_helpers(): void
    {
        $this->assertSame(
            ['present', 'absent', 'late', 'excused'],
            array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::all()),
        );

        foreach (AttendanceStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(AttendanceStatus::Present->isAttending());
        $this->assertTrue(AttendanceStatus::Late->isAttending());
        $this->assertFalse(AttendanceStatus::Absent->isAttending());
        $this->assertFalse(AttendanceStatus::Excused->isAttending());
    }

    public function test_register_status_values_and_lock_helper(): void
    {
        $this->assertSame(['draft', 'submitted'], array_map(fn (AttendanceRegisterStatus $s) => $s->value, AttendanceRegisterStatus::all()));

        foreach (AttendanceRegisterStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertFalse(AttendanceRegisterStatus::Draft->isLocked());
        $this->assertTrue(AttendanceRegisterStatus::Submitted->isLocked());
    }
}
