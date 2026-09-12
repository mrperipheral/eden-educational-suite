<?php

namespace Database\Factories;

use App\Enums\EntryAssessmentStatus;
use App\Models\AcademicLevel;
use App\Models\EntryAssessment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryAssessment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active
 * tenant context — tests must `enterSchool()` first. `assessor_id` /
 * `status` are not `$fillable`; set explicitly via the factory definition.
 */
class EntryAssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => null,
            'candidate_name' => fake()->name(),
            'admission_reference' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => null,
            'subject_id' => Subject::factory(),
            'assessed_on' => now()->toDateString(),
            'score' => null,
            'max_score' => 100,
            'result' => null,
            'notes' => null,
            'assessor_id' => User::factory(),
            'status' => EntryAssessmentStatus::Active->value,
        ];
    }

    public function scored(float $score = 72, float $maxScore = 100): static
    {
        return $this->state(fn () => ['score' => $score, 'max_score' => $maxScore]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => EntryAssessmentStatus::Archived->value]);
    }
}
