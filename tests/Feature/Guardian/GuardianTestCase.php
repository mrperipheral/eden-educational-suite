<?php

namespace Tests\Feature\Guardian;

use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Guardian Management feature tests (M10).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `guardian.manage` (+ view)
 *   - Bursar / Teacher / Staff → `guardian.view` only
 *   - Parent / Student / role-less → no guardian permission → 403
 */
abstract class GuardianTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Turn the Guardians module off for a school (it is on by default). */
    protected function disableGuardians(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'guardians', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * @return array{manage: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function guardianRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'view' => [Role::Bursar, Role::Teacher, Role::Staff],
            'denied' => [Role::Parent, Role::Student, null],
        ];
    }

    /** A student that belongs to the given school. */
    protected function studentFor(School $school, array $attributes = []): Student
    {
        $this->enterSchool($school);
        $student = Student::factory()->create($attributes);
        $this->app->forgetScopedInstances();

        return $student;
    }
}
