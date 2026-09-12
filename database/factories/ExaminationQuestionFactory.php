<?php

namespace Database\Factories;

use App\Enums\ExaminationQuestionType;
use App\Models\Examination;
use App\Models\ExaminationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExaminationQuestion>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `type` is not `$fillable`; set
 * explicitly via the factory definition.
 */
class ExaminationQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'examination_id' => Examination::factory(),
            'question_text' => fake()->sentence().'?',
            'type' => ExaminationQuestionType::MultipleChoice->value,
            'marks' => 1,
            'position' => 0,
        ];
    }
}
