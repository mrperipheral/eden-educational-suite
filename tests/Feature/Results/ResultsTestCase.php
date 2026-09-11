<?php

namespace Tests\Feature\Results;

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
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Results & Report Cards feature tests (M15).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `result.manage` + `.publish` + `.adjust` (+ `.enter` + `.view`)
 *   - Teacher                  → `result.enter` (class-scoped comments) + `.view`
 *   - Staff                    → `result.view` only
 *   - Bursar / Parent / Student / role-less → 403
 *
 * The Results module is **on by default**; {@see self::disableResults()} turns
 * it off. Results depend on Assessments only (never Timetable/Attendance/CBT).
 */
abstract class ResultsTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{manage: list<Role>, enter: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function resultRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'enter' => [Role::Teacher],
            'view' => [Role::Staff],
            'denied' => [Role::Bursar, Role::Parent, Role::Student, null],
        ];
    }

    protected function disableResults(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'results', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A current session + term, a level + arm, two subjects each offered at
     * the level, a "Classwork"/"Exam" category pair, a grading scheme (A/B/F)
     * and a weighting scheme (Classwork 40% / Exam 60%).
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

    /**
     * A locked, fully-scored assessment for the given subject + category, one
     * score per student in `$scores` (keyed by student id, or a plain list
     * matched by position to `$students`).
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

        $students->values()->each(function (Student $student, int $i) use ($assessment, $scores) {
            $score = array_is_list($scores) ? $scores[$i % count($scores)] : ($scores[$student->id] ?? null);
            $assessment->scores()->create(['student_id' => $student->id, 'score' => $score]);
        });

        $assessment->publish();
        $assessment->lock($this->adminUser($school));

        $this->app->forgetScopedInstances();

        return $assessment;
    }

    private function adminUser(School $school): User
    {
        $user = User::factory()->create();
        $user->joinSchool($school, Role::SchoolAdmin);

        return $user;
    }

    /** A draft result run for the scaffold's class + term. */
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

    /** @return array<string, mixed> */
    protected function runPayload(array $scaffold, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'grading_scheme_id' => $scaffold['grading']->id,
            'result_weighting_scheme_id' => $scaffold['weighting']->id,
        ], $overrides);
    }

    /**
     * Create a Teacher-role user with a linked teacher record and an active
     * assignment for the scaffold's class (any subject), authenticate as
     * them, and pin the school context.
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
