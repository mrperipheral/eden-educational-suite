<?php

namespace Database\Factories;

use App\Enums\LearningMaterialType;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterial>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `type`/`file_*`/`uploaded_by`
 * are not `$fillable`; set explicitly via the factory definition (a fake,
 * non-existent file path — tests that need a real stored file use
 * `LearningMaterialUploadService` directly instead of this factory).
 */
class LearningMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'academic_level_id' => AcademicLevel::factory(),
            'subject_id' => Subject::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => LearningMaterialType::Document->value,
            'file_path' => 'learning-materials/fake/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_size' => fake()->numberBetween(1024, 2_000_000),
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'uploaded_by' => User::factory(),
        ];
    }
}
