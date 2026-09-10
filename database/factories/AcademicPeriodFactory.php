<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPeriod>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. Pass `for(AcademicSession)` or set
 * `academic_session_id` so the period has a session.
 */
class AcademicPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;
        $start = fake()->dateTimeBetween('-1 year', '+1 month');

        return [
            'academic_session_id' => AcademicSession::factory(),
            'name' => "Term {$n}",
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => (clone $start)->modify('+3 months')->format('Y-m-d'),
            'position' => $n,
            'is_active' => true,
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true, 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
