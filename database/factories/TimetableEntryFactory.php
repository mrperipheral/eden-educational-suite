<?php

namespace Database\Factories;

use App\Enums\Weekday;
use App\Models\AcademicLevel;
use App\Models\LevelArm;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableEntry>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related record must
 * belong to that same school. Pass explicit ids so the row lines up with a
 * scaffold and its teacher assignment.
 */
class TimetableEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timetable_id' => Timetable::factory(),
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'weekday' => Weekday::Monday->value,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => null,
        ];
    }

    public function at(Weekday $day, string $start, string $end): static
    {
        return $this->state(fn () => [
            'weekday' => $day->value,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
