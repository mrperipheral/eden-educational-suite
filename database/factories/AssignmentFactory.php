<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\LevelArm;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. Pass explicit ids so the row lines
 * up with a scaffold.
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'subject_id' => Subject::factory(),
            'title' => 'Assignment '.$counter,
            'instructions' => null,
            'assigned_on' => now()->toDateString(),
            'due_on' => now()->addWeek()->toDateString(),
            'max_score' => null,
            'status' => AssignmentStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AssignmentStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => AssignmentStatus::Closed->value,
            'published_at' => now(),
        ]);
    }
}
