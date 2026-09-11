<?php

namespace Database\Factories;

use App\Models\AssessmentCategory;
use App\Models\ResultWeightingScheme;
use App\Models\ResultWeightingSchemeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultWeightingSchemeItem>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class ResultWeightingSchemeItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_weighting_scheme_id' => ResultWeightingScheme::factory(),
            'assessment_category_id' => AssessmentCategory::factory(),
            'weight_percentage' => 100,
            'position' => 0,
        ];
    }
}
