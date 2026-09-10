<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local development data: two schools, a member of one, and a platform admin.
     */
    public function run(): void
    {
        $alpha = School::factory()->create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        School::factory()->create(['name' => 'Beta School', 'slug' => 'beta-school']);

        $member = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $member->schools()->attach($alpha);

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
        ]);
    }
}
