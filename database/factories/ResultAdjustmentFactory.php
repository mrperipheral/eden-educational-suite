<?php

namespace Database\Factories;

use App\Enums\ResultAdjustmentStatus;
use App\Models\ResultAdjustment;
use App\Models\StudentSubjectResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultAdjustment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class ResultAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_subject_result_id' => StudentSubjectResult::factory(),
            'field' => 'percentage',
            'original_value' => 60,
            'adjusted_value' => 65,
            'reason' => 'Re-marked script found an addition error.',
            'status' => ResultAdjustmentStatus::Pending->value,
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }
}
