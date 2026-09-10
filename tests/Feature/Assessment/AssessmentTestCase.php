<?php

namespace Tests\Feature\Assessment;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Assignment;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Assessment & Assignments feature tests (M14).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `assessment.manage` (categories, any class, unlock)
 *   - Teacher                  → `assessment.record` (only an assigned class + subject)
 *   - Staff                    → `assessment.view` only
 *   - Bursar / Parent / Student / role-less → 403
 *
 * The Assessments module is **on by default**; {@see self::disableAssessments()}
 * turns it off. Assessments depend on Academics + Students only.
 */
abstract class AssessmentTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{manage: list<Role>, record: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function assessmentRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'record' => [Role::Teacher],
            'view' => [Role::Staff],
            'denied' => [Role::Bursar, Role::Parent, Role::Student, null],
        ];
    }

    protected function disableAssessments(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'assessments', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A current session + term, a level + arm, a subject offered by that level,
     * and one assessment category.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject, category: AssessmentCategory}
     */
    protected function scaffold(School $school): array
    {
        $this->enterSchool($school);

        $session = AcademicSession::factory()->current()->create([
            'starts_on' => now()->subMonths(3)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);
        $period = $session->periods()->create([
            'name' => 'First Term',
            'starts_on' => now()->subMonths(3)->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $subject = Subject::factory()->create();
        $level->subjects()->attach($subject->id, ['school_id' => $school->id]);
        $category = AssessmentCategory::factory()->create();

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject', 'category');
    }

    /**
     * Students enrolled in the scaffold's class from the session start.
     *
     * @return Collection<int, Student>
     */
    protected function enrolledStudents(School $school, array $scaffold, int $count = 3, array $studentAttributes = []): Collection
    {
        $this->enterSchool($school);

        $students = Student::factory()->count($count)->create($studentAttributes)->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]));

        $this->app->forgetScopedInstances();

        return $students;
    }

    /** A draft assessment for the scaffold's class + subject, no scores yet. */
    protected function assessmentFor(School $school, array $scaffold, array $attributes = []): Assessment
    {
        $this->enterSchool($school);

        $assessment = Assessment::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'assessment_category_id' => $scaffold['category']->id,
            'assessment_date' => now()->subDay()->toDateString(),
        ], $attributes));

        $this->app->forgetScopedInstances();

        return $assessment;
    }

    /** A draft assignment for the scaffold's class + subject. */
    protected function assignmentFor(School $school, array $scaffold, array $attributes = []): Assignment
    {
        $this->enterSchool($school);

        $assignment = Assignment::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'assigned_on' => now()->subDays(2)->toDateString(),
            'due_on' => now()->addDays(3)->toDateString(),
        ], $attributes));

        $this->app->forgetScopedInstances();

        return $assignment;
    }

    /** Materialise one score row per eligible student (mimics the store snapshot). */
    protected function snapshotRoster(School $school, Assessment $assessment): void
    {
        $this->enterSchool($school);
        $assessment->eligibleStudents()->get()->each(fn (Student $s) => $assessment->scores()->firstOrCreate(['student_id' => $s->id]));
        $this->app->forgetScopedInstances();
    }

    /** Materialise one pending submission row per eligible student. */
    protected function snapshotSubmissions(School $school, Assignment $assignment): void
    {
        $this->enterSchool($school);
        $assignment->eligibleStudents()->get()->each(fn (Student $s) => $assignment->submissions()->firstOrCreate(['student_id' => $s->id]));
        $this->app->forgetScopedInstances();
    }

    /** @return array<string, mixed> */
    protected function assessmentPayload(array $scaffold, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'assessment_category_id' => $scaffold['category']->id,
            'title' => 'Week 5 Test',
            'assessment_date' => now()->subDay()->toDateString(),
            'max_score' => 20,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    protected function assignmentPayload(array $scaffold, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'title' => 'Fractions worksheet',
            'assigned_on' => now()->subDays(2)->toDateString(),
            'due_on' => now()->addDays(3)->toDateString(),
        ], $overrides);
    }

    /**
     * Create a Teacher-role user with a linked teacher record and an active
     * assignment for the scaffold's class + subject, authenticate as them, and
     * pin the school context.
     */
    protected function actingAsTeacherFor(School $school, array $scaffold, array $assignmentOverrides = []): User
    {
        $this->enterSchool($school);

        $user = User::factory()->create();
        $user->joinSchool($school, Role::Teacher);

        $teacher = Teacher::factory()->create();
        $teacher->user_id = $user->id;
        $teacher->save();

        $teacher->assignments()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ], $assignmentOverrides));

        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }
}
