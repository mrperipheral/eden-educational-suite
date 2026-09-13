<?php

namespace Tests\Feature\Reports;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\AssessmentCategory;
use App\Models\GradingScheme;
use App\Models\LevelArm;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
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
 * Shared setup for the Advanced Reporting & Analytics feature tests (M27).
 *
 * Reporting reuses each domain's own write-side data (M13/M15/M19/M21/M23) —
 * these helpers build that data directly via factories rather than driving
 * the full write-side services, since those are already covered by their
 * own milestones' suites. `reports.view`/`reports.export` are coarse gates
 * always composed with the report's own pre-existing domain permission, so
 * every role helper below mirrors the M4 bundles exactly.
 */
abstract class ReportsTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function disableReports(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'reports', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    protected function disableModule(School $school, string $module): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => $module, 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /** Override an off-by-default module (Cbt, Timetable, LearningMaterials, EntryAssessment) on for this school. */
    protected function enableModule(School $school, string $module): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => $module, 'enabled' => true]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A current session + term, a level + arm, two subjects, a grading
     * scheme and a weighting scheme — the same shape `ResultsTestCase`
     * builds, reused here so `AcademicReport`/`CbtReport` fixtures line up
     * with real M15 result-run data.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject, subject2: Subject, grading: GradingScheme, weighting: ResultWeightingScheme}
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

        $grading = GradingScheme::factory()->create(['name' => 'Standard']);
        $grading->grades()->create(['code' => 'A', 'min_percentage' => 70, 'max_percentage' => 100, 'remark' => 'Excellent', 'position' => 1]);
        $grading->grades()->create(['code' => 'B', 'min_percentage' => 50, 'max_percentage' => 69.99, 'remark' => 'Good', 'position' => 2]);
        $grading->grades()->create(['code' => 'F', 'min_percentage' => 0, 'max_percentage' => 49.99, 'remark' => 'Fail', 'position' => 3]);

        $weighting = ResultWeightingScheme::factory()->create(['name' => 'Standard']);
        $weighting->items()->create(['assessment_category_id' => AssessmentCategory::factory()->create(['name' => 'Exam', 'code' => 'EX', 'position' => 1])->id, 'weight_percentage' => 100, 'position' => 1]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject', 'subject2', 'grading', 'weighting');
    }

    /** A result run for the scaffold's class + term. */
    protected function resultRun(School $school, array $scaffold, array $attributes = []): ResultRun
    {
        $this->enterSchool($school);

        $run = ResultRun::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'grading_scheme_id' => $scaffold['grading']->id,
            'result_weighting_scheme_id' => $scaffold['weighting']->id,
        ], $attributes));

        $this->app->forgetScopedInstances();

        return $run;
    }

    /**
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

    /**
     * Create a Teacher-role user with a linked teacher record and an active
     * assignment for the scaffold's class + subject, authenticate as them,
     * and pin the school context.
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
