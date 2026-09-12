<?php

namespace Tests\Feature\EntryAssessment;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\TeacherAssignmentStatus;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\EntryAssessment;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Entry / Placement Assessment feature tests (M25,
 * `docs/entry-placement-assessment.md`).
 *
 * Roles, from the M4/M25 bundles:
 *   - School Admin → everything.
 *   - Principal     → `.view` + `.record` + `.manage` (full, any class).
 *   - Bursar        → none.
 *   - Teacher       → `.view` + `.record`, scoped to classes/subjects they
 *     hold an active M11 `TeacherAssignment` for.
 *   - Staff         → `.view` only.
 *   - Parent        → no access.
 *   - Student       → no access.
 *
 * The Entry Assessment module is **off by default**; {@see self::enableEntryAssessment()} turns it on.
 */
abstract class EntryAssessmentTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function enableEntryAssessment(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'entry-assessment', 'enabled' => true]);
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
     * A level (+ arm) and a subject offered at that level — the class
     * context an entry assessment record needs.
     *
     * @return array{session: AcademicSession, level: AcademicLevel, arm: LevelArm, subject: Subject}
     */
    protected function classContext(School $school): array
    {
        $this->enterSchool($school);

        $session = AcademicSession::factory()->create();
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $subject = Subject::factory()->create();
        $level->subjects()->attach($subject->id, ['school_id' => $school->id]);

        $this->app->forgetScopedInstances();

        return compact('session', 'level', 'arm', 'subject');
    }

    /**
     * A Teacher-role user with an active M11 assignment for the given
     * (level, arm, subject) — used for `.record` scoping tests.
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
            'started_on' => now()->subMonth()->toDateString(),
        ]);

        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /** A student actively enrolled in the given class context. */
    protected function enrolledStudent(School $school, array $context): Student
    {
        $this->enterSchool($school);

        $student = Student::factory()->create();
        $student->enrollments()->create([
            'academic_session_id' => $context['session']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => now()->subMonth()->toDateString(),
        ]);

        $this->app->forgetScopedInstances();

        return $student;
    }

    /**
     * A record in the given class context.
     *
     * @param  array{level: AcademicLevel, arm?: LevelArm, subject: Subject}  $context
     * @param  array<string, mixed>  $overrides
     */
    protected function assessmentIn(School $school, array $context, array $overrides = []): EntryAssessment
    {
        $this->enterSchool($school);

        $assessment = EntryAssessment::factory()->create(array_merge([
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id ?? null,
            'subject_id' => $context['subject']->id,
        ], $overrides));

        $this->app->forgetScopedInstances();

        return $assessment;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $context, array $overrides = []): array
    {
        return array_merge([
            'candidate_name' => 'Ada Okafor',
            'admission_reference' => 'APP-2026-001',
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id ?? null,
            'subject_id' => $context['subject']->id,
            'assessed_on' => now()->toDateString(),
            'score' => 65,
            'max_score' => 100,
            'result' => 'Pass',
            'notes' => 'Solid grasp of fundamentals.',
        ], $overrides);
    }
}
