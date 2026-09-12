<?php

namespace Database\Factories;

use App\Enums\PromotionRecordStatus;
use App\Models\Enrollment;
use App\Models\PromotionBatch;
use App\Models\PromotionRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionRecord>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. Nothing on `PromotionRecord`
 * is `$fillable` (it is only ever written by `PromotionService`), so every
 * column is set explicitly here — factories bypass mass-assignment
 * guarding, the same as every other system-written-only factory in this
 * codebase.
 */
class PromotionRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_batch_id' => PromotionBatch::factory(),
            'student_id' => Student::factory(),
            'source_enrollment_id' => Enrollment::factory(),
            'status' => PromotionRecordStatus::Promoted->value,
        ];
    }
}
