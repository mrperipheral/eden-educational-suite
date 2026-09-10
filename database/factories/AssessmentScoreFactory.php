<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScore>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and the assessment / student must
 * belong to that same school. Score defaults to null (not entered).
 */
class AssessmentScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'student_id' => Student::factory(),
            'score' => null,
            'comment' => null,
        ];
    }

    public function score(float|int $score): static
    {
        return $this->state(fn () => [
            'score' => $score,
            'recorded_at' => now(),
        ]);
    }
}
