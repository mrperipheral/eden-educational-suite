<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\PromotionBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionBatch>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set explicitly via the factory definition.
 */
class PromotionBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_academic_session_id' => AcademicSession::factory(),
            'source_academic_level_id' => AcademicLevel::factory(),
            'target_academic_session_id' => AcademicSession::factory(),
            'target_academic_level_id' => AcademicLevel::factory(),
            'created_by' => User::factory(),
        ];
    }
}
