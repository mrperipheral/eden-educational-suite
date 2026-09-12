<?php

namespace Database\Factories;

use App\Enums\ResultReleaseMode;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Examination;
use App\Models\LevelArm;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examination>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `status`/`created_by` are not
 * `$fillable`; set explicitly via the factory definition.
 */
class ExaminationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(9, 0);

        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => AcademicPeriod::factory(),
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'subject_id' => Subject::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'duration_minutes' => 30,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(6),
            'pass_mark_percentage' => 50,
            'status' => 'draft',
            'result_release' => ResultReleaseMode::Immediate->value,
            'created_by' => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'scheduled_at' => now()->subHour(),
            'closed_at' => now(),
        ]);
    }

    public function scheduledRelease(): static
    {
        return $this->state(fn () => [
            'result_release' => ResultReleaseMode::Scheduled->value,
            'result_release_at' => now()->addDay(),
        ]);
    }
}
