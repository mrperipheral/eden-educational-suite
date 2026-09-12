<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `status`/`score`/`max_score`/
 * `percentage`/`passed` are not `$fillable`; set explicitly here or via
 * state methods.
 */
class ExamAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now();

        return [
            'examination_id' => Examination::factory(),
            'student_id' => Student::factory(),
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes(30),
            'status' => 'in_progress',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'submitted_at' => now(),
            'score' => 0,
            'max_score' => 0,
            'percentage' => 0,
            'passed' => false,
        ]);
    }

    public function expired(): static
    {
        $startedAt = now()->subHours(2);

        return $this->state(fn () => [
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes(30),
        ]);
    }
}
