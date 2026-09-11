<?php

namespace Database\Factories;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationMessage>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first, and the thread must belong to
 * that same school.
 */
class CommunicationMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_thread_id' => CommunicationThread::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
