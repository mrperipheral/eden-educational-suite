<?php

namespace Tests\Unit\Enums;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use Tests\TestCase;

class StudentEnumsTest extends TestCase
{
    public function test_student_status_values_and_labels(): void
    {
        $this->assertSame(
            ['active', 'inactive', 'withdrawn', 'graduated'],
            array_map(fn (StudentStatus $s) => $s->value, StudentStatus::all()),
        );

        foreach (StudentStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(StudentStatus::Active->isEnrolled());
        $this->assertTrue(StudentStatus::Inactive->isEnrolled());
        $this->assertFalse(StudentStatus::Withdrawn->isEnrolled());
        $this->assertFalse(StudentStatus::Graduated->isEnrolled());
    }

    public function test_enrollment_status_values_and_labels(): void
    {
        $this->assertSame(
            ['active', 'completed', 'withdrawn'],
            array_map(fn (EnrollmentStatus $s) => $s->value, EnrollmentStatus::all()),
        );

        foreach (EnrollmentStatus::all() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->badgeVariant());
        }

        $this->assertTrue(EnrollmentStatus::Active->isOpen());
        $this->assertFalse(EnrollmentStatus::Completed->isOpen());
    }

    public function test_gender_is_a_small_closed_set(): void
    {
        $this->assertSame(['male', 'female', 'other'], array_map(fn (Gender $g) => $g->value, Gender::all()));

        foreach (Gender::all() as $gender) {
            $this->assertNotSame('', $gender->label());
        }
    }
}
