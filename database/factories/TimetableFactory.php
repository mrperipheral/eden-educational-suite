<?php

namespace Database\Factories;

use App\Enums\TimetableStatus;
use App\Models\AcademicSession;
use App\Models\Timetable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timetable>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and the session must belong to
 * that same school.
 */
class TimetableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'name' => "Timetable {$n}",
            'status' => TimetableStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TimetableStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
