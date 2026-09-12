<?php

namespace Database\Factories;

use App\Models\ExaminationQuestion;
use App\Models\ExaminationQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationQuestionOption>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class ExaminationQuestionOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'examination_question_id' => ExaminationQuestion::factory(),
            'option_text' => fake()->words(2, true),
            'is_correct' => false,
            'position' => 0,
        ];
    }
}
