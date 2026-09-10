<?php

namespace Database\Factories;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_id' => Student::factory(),
            'status' => AssignmentSubmissionStatus::Pending->value,
            'submitted_on' => null,
            'remark' => null,
        ];
    }

    public function status(AssignmentSubmissionStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->value,
            'recorded_at' => now(),
        ]);
    }
}
