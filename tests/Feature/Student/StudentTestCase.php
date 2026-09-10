<?php

namespace Tests\Feature\Student;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Student Management feature tests (M9).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `student.manage` (+ view)
 *   - Bursar / Teacher / Staff → `student.view` only
 *   - Parent / Student / role-less → no student permission → 403
 */
abstract class StudentTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Turn the Students module off for a school (it is on by default). */
    protected function disableStudents(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'students', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * @return array{manage: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function studentRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'view' => [Role::Bursar, Role::Teacher, Role::Staff],
            'denied' => [Role::Parent, Role::Student, null],
        ];
    }

    /**
     * Build a minimal academic structure for a school so enrollments have
     * something to point at.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm}
     */
    protected function scaffold(School $school): array
    {
        $this->enterSchool($school);

        $session = AcademicSession::factory()->current()->create();
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => '2025-09-15', 'ends_on' => '2025-12-12', 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm');
    }
}
