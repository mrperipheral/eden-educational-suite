<?php

namespace Tests\Feature\Teacher;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Teacher Management feature tests (M11).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `staff.manage` (+ view)
 *   - Bursar / Teacher / Staff → `staff.view` only
 *   - Parent / Student / role-less → no staff permission → 403
 */
abstract class TeacherTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Turn the Staff (teachers) module off for a school (it is on by default). */
    protected function disableStaff(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'staff', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * @return array{manage: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function teacherRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'view' => [Role::Bursar, Role::Teacher, Role::Staff],
            'denied' => [Role::Parent, Role::Student, null],
        ];
    }

    /** A teacher that belongs to the given school. */
    protected function teacherFor(School $school, array $attributes = []): Teacher
    {
        $this->enterSchool($school);
        $teacher = Teacher::factory()->create($attributes);
        $this->app->forgetScopedInstances();

        return $teacher;
    }

    /**
     * Build a minimal academic structure so assignments have something to point at.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject}
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
        $subject = Subject::factory()->create();

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject');
    }
}
