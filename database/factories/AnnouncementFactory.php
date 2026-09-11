<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set it explicitly.
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
            'audience' => AnnouncementAudience::Everyone->value,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
