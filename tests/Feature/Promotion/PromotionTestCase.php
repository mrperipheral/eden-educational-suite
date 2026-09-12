<?php

namespace Tests\Feature\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Promotion & Graduation feature tests (M21,
 * `docs/promotion.md`).
 *
 * Roles, from the M4/M21 bundles:
 *   - School Admin → everything.
 *   - Principal     → `promotion.view` + `.manage` + `graduation.manage` (full operational).
 *   - Bursar        → none.
 *   - Teacher/Staff → `promotion.view` only, no execution.
 *   - Parent/Student → no admin access; they see their own current placement
 *     through the existing portal (not a `promotion.*` permission at all).
 *
 * The Promotion module is **on by default**; {@see self::disablePromotion()} turns it off.
 */
abstract class PromotionTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function disablePromotion(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'promotion', 'enabled' => false]);
        $this->app->forgetScopedInstances();
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
     * A session (+ term) and a level (+ arm) — a class context a student can
     * be enrolled into. Pass an existing `$session` to build a second class
     * context (e.g. a different level) inside the same session.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm}
     */
    protected function classContext(School $school, ?AcademicSession $session = null): array
    {
        $this->enterSchool($school);

        $session ??= AcademicSession::factory()->create();
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => $session->starts_on->toDateString(), 'ends_on' => $session->starts_on->copy()->addMonths(3)->toDateString(), 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm');
    }

    /** A student actively enrolled in the given class context. */
    protected function enrolledStudent(School $school, array $context, array $studentAttributes = [], array $enrollmentOverrides = []): Student
    {
        $this->enterSchool($school);

        $student = Student::factory()->create($studentAttributes);
        $student->enrollments()->create(array_merge([
            'academic_session_id' => $context['session']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id ?? null,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $context['session']->starts_on->toDateString(),
        ], $enrollmentOverrides));

        $this->app->forgetScopedInstances();

        return $student;
    }
}
