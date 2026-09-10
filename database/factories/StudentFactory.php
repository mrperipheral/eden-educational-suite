<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `admission_number` is unique per
 * generated row so `unique(school_id, admission_number)` never trips.
 */
class StudentFactory extends Factory
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
            'date_of_birth' => fake()->dateTimeBetween('-16 years', '-4 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases())->value,
            'admission_number' => "STU-{$n}",
            'admitted_on' => fake()->dateTimeBetween('-4 years', 'now')->format('Y-m-d'),
            'status' => StudentStatus::Active->value,
            'contact_email' => fake()->optional()->safeEmail(),
            'contact_phone' => fake()->optional()->numerify('+234#########'),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->randomElement(['Lagos', 'Oyo', 'Abuja', 'Rivers', 'Kano']),
            'notes' => null,
        ];
    }

    public function status(StudentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
