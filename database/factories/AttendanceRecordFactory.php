<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and the register / student must
 * belong to that same school. Status defaults to null (unmarked).
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_register_id' => AttendanceRegister::factory(),
            'student_id' => Student::factory(),
            'status' => null,
            'note' => null,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->value,
            'recorded_at' => now(),
        ]);
    }
}
