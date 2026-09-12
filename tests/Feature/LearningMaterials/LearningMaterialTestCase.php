<?php

namespace Tests\Feature\LearningMaterials;

use App\Enums\Role;
use App\Enums\TeacherAssignmentStatus;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Learning Materials feature tests (M22,
 * `docs/learning-materials.md`).
 *
 * Roles, from the M4/M22 bundles:
 *   - School Admin → everything.
 *   - Principal     → `.view` + `.upload` + `.manage` (full, any class).
 *   - Bursar        → none.
 *   - Teacher       → `.view` + `.upload`, scoped to classes/subjects they
 *     hold an active M11 `TeacherAssignment` for.
 *   - Staff         → `.view` only.
 *   - Parent/Student → no admin access; a student reaches their own current
 *     class's materials through the Student Portal (`portal.student`).
 *
 * The Learning Materials module is **off by default**; {@see self::enableLearningMaterials()} turns it on.
 */
abstract class LearningMaterialTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function enableLearningMaterials(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'learning-materials', 'enabled' => true]);
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
     * A session (+ term), a level (+ arm) and a subject offered at that
     * level — the full class context a material needs.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject}
     */
    protected function classContext(School $school): array
    {
        $this->enterSchool($school);

        $session = AcademicSession::factory()->create();
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => $session->starts_on->toDateString(), 'ends_on' => $session->starts_on->copy()->addMonths(3)->toDateString(), 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $subject = Subject::factory()->create();
        $level->subjects()->attach($subject->id, ['school_id' => $school->id]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject');
    }

    /**
     * A Teacher-role user with an active M11 assignment for the given
     * (level, arm, subject) — used for `material.upload` scoping tests.
     */
    protected function teacherAssignedTo(School $school, array $context, ?LevelArm $arm = null): User
    {
        $this->enterSchool($school);

        $user = User::factory()->create();
        $user->joinSchool($school, Role::Teacher);

        $teacher = Teacher::factory()->create();
        $teacher->user_id = $user->id;
        $teacher->save();

        $teacher->assignments()->create([
            'academic_session_id' => $context['session']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $arm?->id,
            'subject_id' => $context['subject']->id,
            'status' => TeacherAssignmentStatus::Active->value,
            'started_on' => $context['session']->starts_on->toDateString(),
        ]);

        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }
}
