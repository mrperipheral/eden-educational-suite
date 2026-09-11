<?php

namespace Database\Factories;

use App\Enums\ResultRunStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\GradingScheme;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultRun>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related record must
 * belong to that same school. Pass explicit ids so the row lines up with a
 * scaffold.
 */
class ResultRunFactory extends Factory
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
            'grading_scheme_id' => GradingScheme::factory(),
            'result_weighting_scheme_id' => ResultWeightingScheme::factory(),
            'ranking_enabled' => true,
            'status' => ResultRunStatus::Draft->value,
        ];
    }

    public function compiled(): static
    {
        return $this->state(fn () => ['status' => ResultRunStatus::Compiled->value, 'compiled_at' => now()]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ResultRunStatus::Published->value,
            'compiled_at' => now(), 'reviewed_at' => now(), 'approved_at' => now(), 'published_at' => now(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => ResultRunStatus::Locked->value,
            'compiled_at' => now(), 'reviewed_at' => now(), 'approved_at' => now(), 'published_at' => now(), 'locked_at' => now(),
        ]);
    }
}
