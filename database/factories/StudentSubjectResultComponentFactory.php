<?php

namespace Database\Factories;

use App\Models\AssessmentCategory;
use App\Models\StudentSubjectResult;
use App\Models\StudentSubjectResultComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSubjectResultComponent>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class StudentSubjectResultComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_subject_result_id' => StudentSubjectResult::factory(),
            'assessment_category_id' => AssessmentCategory::factory(),
            'category_name_snapshot' => 'Classwork',
            'weight_percentage_snapshot' => 100,
            'raw_score' => 7,
            'raw_max_score' => 10,
            'score_percentage' => 70,
            'weighted_contribution' => 70,
            'position' => 0,
        ];
    }
}
