<?php

namespace Tests\Unit\Enums;

use App\Enums\TeacherAssignmentStatus;
use App\Enums\TeacherStatus;
use Tests\TestCase;

class TeacherEnumsTest extends TestCase
{
    public function test_teacher_status_values_and_labels(): void
    {
        $this->assertSame(
            ['active', 'inactive', 'suspended', 'resigned'],
            array_map(fn (TeacherStatus $s) => $s->value, TeacherStatus::all()),
        );

        foreach (TeacherStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(TeacherStatus::Active->isEmployed());
        $this->assertTrue(TeacherStatus::Inactive->isEmployed());
        $this->assertTrue(TeacherStatus::Suspended->isEmployed());
        $this->assertFalse(TeacherStatus::Resigned->isEmployed());
    }

    public function test_assignment_status_values_and_labels(): void
    {
        $this->assertSame(
            ['active', 'ended'],
            array_map(fn (TeacherAssignmentStatus $s) => $s->value, TeacherAssignmentStatus::all()),
        );

        foreach (TeacherAssignmentStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(TeacherAssignmentStatus::Active->isOpen());
        $this->assertFalse(TeacherAssignmentStatus::Ended->isOpen());
    }
}
