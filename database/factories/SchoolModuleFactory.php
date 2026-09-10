<?php

namespace Database\Factories;

use App\Enums\Module;
use App\Models\SchoolModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolModule>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first.
 */
class SchoolModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => fake()->randomElement(Module::cases())->value,
            'enabled' => fake()->boolean(),
        ];
    }

    public function forModule(Module $module): static
    {
        return $this->state(fn () => ['module' => $module->value]);
    }

    public function enabled(bool $enabled = true): static
    {
        return $this->state(fn () => ['enabled' => $enabled]);
    }

    public function disabled(): static
    {
        return $this->enabled(false);
    }
}
