<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\CommunicationThread;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Communication & Notification Foundation feature tests
 * (M18, `docs/communication.md`).
 *
 * Roles, from the M4/M18 bundles:
 *   - School Admin / Principal → `communication.manage` (+ resolve/escalate)
 *   - Teacher                  → `communication.resolve` + `.escalate`, not `.manage`
 *   - Bursar / Staff           → `communication.view` + `.create` only
 *   - Parent / Student / role-less → 403 (the Communication Hub is staff-only)
 *
 * The Notifications module is **on by default**; {@see self::disableNotifications()}
 * turns it off.
 */
abstract class CommunicationTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function disableNotifications(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'notifications', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    protected function threadIn(School $school, array $attributes = []): CommunicationThread
    {
        $this->enterSchool($school);

        $creator = $attributes['created_by'] ?? User::factory()->create()->getKey();
        unset($attributes['created_by']);

        $thread = new CommunicationThread(array_merge(['subject' => 'Test thread'], $attributes));
        $thread->created_by = $creator;
        $thread->save();

        $this->app->forgetScopedInstances();

        return $thread;
    }

    protected function studentIn(School $school, array $attributes = []): Student
    {
        $this->enterSchool($school);
        $student = Student::factory()->create($attributes);
        $this->app->forgetScopedInstances();

        return $student;
    }

    /**
     * Authenticate as a member of $school with $role and pin the session's
     * active tenant — mirrors `actingAsMemberOf()` (kept local so the
     * Communication test suite reads standalone).
     */
    protected function actingAsRole(School $school, Role $role): User
    {
        $user = User::factory()->create();
        $user->joinSchool($school, $role);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }
}
