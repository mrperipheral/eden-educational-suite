<?php

namespace Database\Factories;

use App\Models\SchoolSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolSetting>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class SchoolSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'timezone' => 'Africa/Lagos',
            'locale' => 'en',
            'country' => 'NG',
            'currency' => 'NGN',
            'date_format' => 'd/m/Y',
            'week_starts_on' => 1,
            'academic_year_start_month' => 9,
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->numerify('+234#########'),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['Lagos', 'Oyo', 'Abuja', 'Rivers', 'Kano']),
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
