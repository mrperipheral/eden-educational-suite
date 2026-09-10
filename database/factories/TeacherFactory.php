<?php

namespace Database\Factories;

use App\Enums\TeacherStatus;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `employee_number` is unique per
 * generated row so `unique(school_id, employee_number)` never trips.
 */
class TeacherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = str_pad((string) (++$counter), 4, '0', STR_PAD_LEFT);

        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'preferred_name' => null,
            'employee_number' => "EMP-{$n}",
            'email' => "teacher{$n}@example.test",
            'phone' => fake()->numerify('+234#########'),
            'employed_on' => fake()->dateTimeBetween('-8 years', 'now')->format('Y-m-d'),
            'status' => TeacherStatus::Active->value,
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->randomElement(['Lagos', 'Oyo', 'Abuja', 'Rivers', 'Kano']),
            'notes' => null,
        ];
    }

    public function status(TeacherStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
