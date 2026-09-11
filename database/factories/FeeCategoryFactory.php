<?php

namespace Database\Factories;

use App\Models\FeeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeCategory>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class FeeCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'name' => 'Fee Category '.$n,
            'code' => 'FC'.$n,
            'description' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }
}
