<?php

namespace Database\Factories;

use App\Models\ResultWeightingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultWeightingScheme>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class ResultWeightingSchemeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'name' => 'Weighting Scheme '.$counter,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
