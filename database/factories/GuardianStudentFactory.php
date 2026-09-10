<?php

namespace Database\Factories;

use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuardianStudent>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and the student / guardian must
 * belong to that same school.
 */
class GuardianStudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'guardian_id' => Guardian::factory(),
            'relationship' => fake()->randomElement(GuardianRelationship::cases())->value,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function relationship(GuardianRelationship $relationship): static
    {
        return $this->state(fn () => ['relationship' => $relationship->value]);
    }
}
