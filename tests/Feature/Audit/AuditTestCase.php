<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Administration & Audit feature tests (M26,
 * `docs/audit.md`).
 *
 * Roles, from the M4/M26 bundles:
 *   - School Admin → full audit access (automatic, `Permission::all()`).
 *   - Principal     → `audit.view`.
 *   - Bursar/Teacher/Staff → no audit access (not granted by any bundle).
 *   - Parent/Student → no access.
 */
abstract class AuditTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function actingAsRole(School $school, ?Role $role): User
    {
        $user = User::factory()->create();
        $user->joinSchool($school, $role);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /**
     * The most recent audit row for an event, regardless of school (tests
     * that need it tenant-scoped filter further themselves).
     */
    protected function latestAuditFor(string $event): ?AuditLog
    {
        return AuditLog::query()->where('event', $event)->orderByDesc('id')->first();
    }
}
