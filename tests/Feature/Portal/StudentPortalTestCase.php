<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\GradingScheme;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Results\ResultCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Student Portal feature tests (M17). Mirrors
 * `Tests\Feature\Portal\ParentPortalTestCase` (M16) — a student has at most
 * one linked `Student` record, so there is no "which child" question, only
 * "is this really them."
 */
abstract class StudentPortalTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{student: list<Role>, denied: list<Role|null>}
     */
    protected function studentPortalRoles(): array
    {
        return [
            'student' => [Role::Student],
            'denied' => [Role::Principal, Role::Bursar, Role::Teacher, Role::Staff, Role::Parent, null],
        ];
    }

    protected function disableStudentPortal(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'student-portal', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A Student-role user, linked to one newly created `Student` record.
     *
     * @return array{0: User, 1: Student}
     */
    protected function studentWithAccount(School $school, array $studentAttributes = []): array
    {
        $this->enterSchool($school);

        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);

        $student = Student::factory()->create($studentAttributes);
        $student->user_id = $user->id;
        $student->save();

        $this->app->forgetScopedInstances();

        return [$user, $student];
    }

    /** Authenticate as an existing user and pin `$school` as the active tenant, as EnforceTenant would. */
    protected function actingAsStudentUser(School $school, User $user): User
    {
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /**
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject, subject2: Subject, classwork: AssessmentCategory, exam: AssessmentCategory, grading: GradingScheme, weighting: ResultWeightingScheme}
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
        $subject2 = Subject::factory()->create();
        $level->subjects()->attach([$subject->id, $subject2->id], ['school_id' => $school->id]);

        $classwork = AssessmentCategory::factory()->create(['name' => 'Classwork', 'code' => 'CW', 'position' => 1]);
        $exam = AssessmentCategory::factory()->create(['name' => 'Exam', 'code' => 'EX', 'position' => 2]);

        $grading = GradingScheme::factory()->create(['name' => 'Standard']);
        $grading->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'remark' => 'Excellent', 'position' => 1]);
        $grading->grades()->create(['code' => 'B', 'min_percentage' => 50, 'max_percentage' => 69.99, 'remark' => 'Good', 'position' => 2]);
        $grading->grades()->create(['code' => 'F', 'min_percentage' => 0, 'max_percentage' => 49.99, 'remark' => 'Fail', 'position' => 3]);

        $weighting = ResultWeightingScheme::factory()->create(['name' => 'Standard']);
        $weighting->items()->create(['assessment_category_id' => $classwork->id, 'weight_percentage' => 40, 'position' => 1]);
        $weighting->items()->create(['assessment_category_id' => $exam->id, 'weight_percentage' => 60, 'position' => 2]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject', 'subject2', 'classwork', 'exam', 'grading', 'weighting');
    }

    protected function enrollInScaffold(School $school, array $scaffold, Collection $students): void
    {
        $this->enterSchool($school);

        $students->each(fn (Student $s) => $s->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => EnrollmentStatus::Active->value,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]));

        $this->app->forgetScopedInstances();
    }

    protected function lockedAssessment(School $school, array $scaffold, Subject $subject, AssessmentCategory $category, Collection $students, array $scores, array $overrides = []): Assessment
    {
        $this->enterSchool($school);

        $assessment = Assessment::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $subject->id,
            'assessment_category_id' => $category->id,
            'max_score' => 20,
        ], $overrides));

        $students->values()->each(fn (Student $student, int $i) => $assessment->scores()->create([
            'student_id' => $student->id,
            'score' => $scores[$i % count($scores)],
        ]));

        $assessment->publish();
        $assessment->lock($this->adminUser($school));

        $this->app->forgetScopedInstances();

        return $assessment;
    }

    protected function adminUser(School $school): User
    {
        $user = User::factory()->create();
        $user->joinSchool($school, Role::SchoolAdmin);

        return $user;
    }

    protected function publishedRun(School $school, array $scaffold, Collection $students, bool $locked = true): ResultRun
    {
        $this->enterSchool($school);
        $admin = $this->adminUser($school);
        $this->app->forgetScopedInstances();

        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);

        $this->enterSchool($school);
        $run = ResultRun::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'grading_scheme_id' => $scaffold['grading']->id,
            'result_weighting_scheme_id' => $scaffold['weighting']->id,
        ]);
        app(ResultCompiler::class)->compile($run, $admin);
        $run->refresh();
        $run->review($admin);
        $run->approve($admin);
        $run->publish($admin);

        if ($locked) {
            $run->lock($admin);
        }

        $this->app->forgetScopedInstances();

        return $run;
    }
}
