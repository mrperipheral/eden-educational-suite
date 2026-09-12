<?php

namespace Database\Factories;

use App\Enums\ExaminationQuestionType;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `type`/`created_by` are not
 * `$fillable`; set explicitly via the factory definition.
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'question_text' => fake()->sentence().'?',
            'type' => ExaminationQuestionType::MultipleChoice->value,
            'marks' => 1,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function trueFalse(): static
    {
        return $this->state(fn () => ['type' => ExaminationQuestionType::TrueFalse->value]);
    }
}
