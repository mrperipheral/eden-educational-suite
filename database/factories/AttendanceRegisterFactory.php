<?php

namespace Database\Factories;

use App\Enums\AttendanceRegisterStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\AttendanceRegister;
use App\Models\LevelArm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRegister>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related academic record
 * must belong to that same school. Pass explicit ids so the row lines up with a
 * scaffold.
 */
class AttendanceRegisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceRegisterStatus::Draft->value,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => AttendanceRegisterStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }
}
