<?php

namespace Database\Factories;

use App\Models\GradingScheme;
use App\Models\GradingSchemeGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingSchemeGrade>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class GradingSchemeGradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'grading_scheme_id' => GradingScheme::factory(),
            'code' => 'G'.$counter,
            'min_percentage' => 0,
            'max_percentage' => 100,
            'remark' => null,
            'position' => $counter,
            'is_active' => true,
        ];
    }
}
