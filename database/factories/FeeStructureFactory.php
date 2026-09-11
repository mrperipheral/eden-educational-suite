<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set it explicitly via state when using this factory directly.
 */
class FeeStructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_category_id' => FeeCategory::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'academic_level_id' => AcademicLevel::factory(),
            'created_by' => User::factory(),
            'amount' => 5000,
            'is_mandatory' => true,
            'is_active' => true,
        ];
    }
}
