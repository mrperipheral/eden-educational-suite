<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data:
     *   - Alpha Academy: fully onboarded (admin, settings, current session).
     *   - Beta School: freshly provisioned (no admin, no settings, no session).
     *   - a platform admin, and a spread of per-school roles.
     */
    public function run(): void
    {
        $alpha = School::factory()->create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        $beta = School::factory()->create(['name' => 'Beta School', 'slug' => 'beta-school']);

        User::factory()->platformAdmin()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@example.com',
        ]);

        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com'])
            ->joinSchool($alpha, Role::SchoolAdmin);

        User::factory()->create(['name' => 'Priya Principal', 'email' => 'principal@example.com'])
            ->joinSchool($alpha, Role::Principal);

        User::factory()->create(['name' => 'Tomiwa Teacher', 'email' => 'teacher@example.com'])
            ->joinSchool($alpha, Role::Teacher);

        User::factory()->create(['name' => 'Bola Bursar', 'email' => 'bursar@example.com'])
            ->joinSchool($alpha, Role::Bursar);

        // Teacher at Alpha, Parent at Beta — the cross-school role case.
        $dual = User::factory()->create(['name' => 'Dele Dual', 'email' => 'dual@example.com']);
        $dual->joinSchool($alpha, Role::Staff);
        $dual->joinSchool($beta, Role::Parent);

        // Alpha's school-owned onboarding data (created inside its tenant context).
        $tenant = app(TenantContext::class);
        $tenant->set($alpha);

        $alpha->settings()->firstOrCreate([], ['contact_email' => 'office@alpha.example'])->markReviewed();
        AcademicSession::create([
            'name' => '2025/2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-07-31',
        ])->makeCurrent();

        $tenant->forget();
    }
}
