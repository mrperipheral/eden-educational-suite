<?php

namespace Tests\Feature\Portal;

use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\GradingScheme;
use App\Models\Guardian;
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
 * Shared setup for the Parent Portal feature tests (M16).
 *
 * Roles: only `Role::Parent` holds `portal.parent` on its own (School Admin
 * also does, via its full permission bundle, but is tested separately since
 * it never has a linked Guardian). Every other role is denied.
 *
 * The Parent Portal module is **on by default**; {@see self::disableParentPortal()}
 * turns it off. It depends on Guardians only.
 */
abstract class ParentPortalTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{parent: list<Role>, denied: list<Role|null>}
     */
    protected function parentPortalRoles(): array
    {
        return [
            'parent' => [Role::Parent],
            'denied' => [Role::Principal, Role::Bursar, Role::Teacher, Role::Staff, Role::Student, null],
        ];
    }

    protected function disableParentPortal(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'parent-portal', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A Parent-role user, linked via a Guardian record to `$childCount` newly
     * enrolled students in `$school`.
     *
     * @return array{0: User, 1: Guardian, 2: Collection<int, Student>}
     */
    protected function parentWithChildren(School $school, int $childCount = 1, array $studentAttributes = []): array
    {
        $this->enterSchool($school);

        $user = User::factory()->create();
        $user->joinSchool($school, Role::Parent);

        $guardian = Guardian::factory()->create();
        // `user_id` is deliberately not fillable — set it directly, exactly
        // like GuardianController::updateUser() does.
        $guardian->user_id = $user->id;
        $guardian->save();

        $students = Student::factory()->count($childCount)->create($studentAttributes);
        $students->each(fn (Student $s) => $s->guardianLinks()->create([
            'guardian_id' => $guardian->id,
            'relationship' => GuardianRelationship::Mother->value,
            'is_primary' => true,
        ]));

        $this->app->forgetScopedInstances();

        return [$user, $guardian, $students];
    }

    /** Authenticate as an existing user and pin `$school` as the active tenant, as EnforceTenant would. */
    protected function actingAsParent(School $school, User $user): User
    {
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /**
     * A current session + term, a level + arm, two subjects each offered at
     * the level, a "Classwork"/"Exam" category pair, a grading scheme (A/B/F)
     * and a weighting scheme (Classwork 40% / Exam 60%) — the same shape M15's
     * own test suite uses.
     *
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

    /** Enroll `$students` into the scaffold's class from the session start. */
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

    /**
     * A locked, fully-scored assessment for the given subject + category, one
     * score per student in `$scores` (a plain list matched by position).
     */
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

    /**
     * A fully compiled, reviewed, approved, published (optionally locked)
     * result run for the scaffold's class, for `$students` (each scoring 50%
     * on Classwork and 100% on Exam => 80% / grade A).
     */
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
