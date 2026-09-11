<?php

namespace Database\Factories;

use App\Models\ReportCardConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCardConfiguration>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. Every `show_*` field defaults
 * to visible via the model's own attribute defaults.
 */
class ReportCardConfigurationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => null,
            'academic_period_id' => null,
        ];
    }
}
