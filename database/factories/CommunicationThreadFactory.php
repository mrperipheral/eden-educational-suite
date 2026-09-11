<?php

namespace Database\Factories;

use App\Enums\CommunicationCategory;
use App\Models\CommunicationThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationThread>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set it explicitly via state when the factory is used directly
 * (controllers set it from the authenticated user instead).
 */
class CommunicationThreadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'category' => fake()->randomElement(CommunicationCategory::all())->value,
            'subject' => fake()->sentence(6),
        ];
    }
}
