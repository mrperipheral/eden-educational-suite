<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local development data: two schools, a platform admin, and a spread of
     * per-school roles so every authorization path can be exercised by hand.
     */
    public function run(): void
    {
        $alpha = School::factory()->create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        $beta = School::factory()->create(['name' => 'Beta School', 'slug' => 'beta-school']);

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
        ]);

        // A school admin at Alpha (also the "Test User" other milestones referenced).
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com'])
            ->joinSchool($alpha, Role::SchoolAdmin);

        User::factory()->create(['name' => 'Priya Principal', 'email' => 'principal@example.com'])
            ->joinSchool($alpha, Role::Principal);

        User::factory()->create(['name' => 'Tomiwa Teacher', 'email' => 'teacher@example.com'])
            ->joinSchool($alpha, Role::Teacher);

        User::factory()->create(['name' => 'Bola Bursar', 'email' => 'bursar@example.com'])
            ->joinSchool($alpha, Role::Bursar);

        // Someone who is a Teacher at Alpha and a Parent at Beta — the
        // cross-school role case.
        $dual = User::factory()->create(['name' => 'Dele Dual', 'email' => 'dual@example.com']);
        $dual->joinSchool($alpha, Role::Staff);
        $dual->joinSchool($beta, Role::Parent);
    }
}
