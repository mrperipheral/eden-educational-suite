<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class GuardianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'preferred_name' => null,
            'email' => "guardian{$n}@example.test",
            'phone' => fake()->numerify('+234#########'),
            'alt_phone' => fake()->optional()->numerify('+234#########'),
            'address_line1' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->randomElement(['Lagos', 'Oyo', 'Abuja', 'Rivers', 'Kano']),
            'notes' => null,
        ];
    }
}
