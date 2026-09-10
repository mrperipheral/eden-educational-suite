<?php

namespace Tests\Feature\Academic;

use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Academic Foundation feature tests (M8).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `academics.manage` (+ view)
 *   - Teacher / Staff          → `academics.view` only
 *   - Bursar / Parent / Student → no academic permission → 403
 */
abstract class AcademicTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Turn the Academic module off for a school (it is on by default).
     */
    protected function disableAcademics(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'academics', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * Roles that may manage academic config, and roles that may only read it.
     *
     * @return array{manage: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function academicRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'view' => [Role::Teacher, Role::Staff],
            'denied' => [Role::Bursar, Role::Parent, Role::Student, null],
        ];
    }
}
