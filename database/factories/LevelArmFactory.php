<?php

namespace Database\Factories;

use App\Models\AcademicLevel;
use App\Models\LevelArm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LevelArm>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. Pass `for(AcademicLevel)` or set
 * `academic_level_id`.
 */
class LevelArmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'academic_level_id' => AcademicLevel::factory(),
            'name' => "Arm {$n}",
            'code' => "A{$n}",
            'position' => $n,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
