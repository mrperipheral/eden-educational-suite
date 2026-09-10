<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\LevelArm;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and every related academic record
 * must belong to that same school. Pass explicit ids so the row lines up with a
 * scaffold.
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_period_id' => null,
            'academic_level_id' => AcademicLevel::factory(),
            'level_arm_id' => LevelArm::factory(),
            'subject_id' => Subject::factory(),
            'assessment_category_id' => AssessmentCategory::factory(),
            'assignment_id' => null,
            'title' => 'Assessment '.$counter,
            'assessment_date' => now()->toDateString(),
            'max_score' => 20,
            'instructions' => null,
            'status' => AssessmentStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AssessmentStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => AssessmentStatus::Locked->value,
            'published_at' => now(),
            'locked_at' => now(),
        ]);
    }
}
