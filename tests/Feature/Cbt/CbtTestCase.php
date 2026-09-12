<?php

namespace Tests\Feature\Cbt;

use App\Enums\EnrollmentStatus;
use App\Enums\ExaminationQuestionType;
use App\Enums\Role;
use App\Enums\TeacherAssignmentStatus;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Examination;
use App\Models\LevelArm;
use App\Models\Question;
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
 * Shared setup for the CBT / Online Examinations feature tests (M23,
 * `docs/cbt.md`).
 *
 * Roles, from the M4/M23 bundles:
 *   - School Admin → everything.
 *   - Principal     → `.view` + `.author` + `.manage` (full, any class).
 *   - Bursar        → none.
 *   - Teacher       → `.view` + `.author`, scoped to classes/subjects they
 *     hold an active M11 `TeacherAssignment` for.
 *   - Staff         → `.view` only.
 *   - Parent        → no access.
 *   - Student       → `cbt.take` — their own current class's examinations only.
 *
 * The CBT module is **off by default**; {@see self::enableCbt()} turns it on.
 */
abstract class CbtTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function enableCbt(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'cbt', 'enabled' => true]);
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
     * level — the full class context an examination needs.
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
     * (level, arm, subject) — used for `cbt.author` scoping tests.
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
            'started_on' => $context['session']->starts_on->toDateString(),
        ]);

        $this->app->forgetScopedInstances();

        return $student;
    }

    /** A student-role user linked to a fresh, enrolled Student record. */
    protected function studentUserFor(School $school, array $context): array
    {
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return [$user, $student];
    }

    /**
     * A reusable bank Question with options, in the given subject.
     *
     * @param  list<array{text: string, correct: bool}>|null  $options
     * @param  array{academic_level_id?: ?int, level_arm_id?: ?int, topic?: ?string, status?: string, difficulty?: string}  $overrides
     */
    protected function questionIn(School $school, Subject $subject, ?array $options = null, ExaminationQuestionType $type = ExaminationQuestionType::MultipleChoice, array $overrides = []): Question
    {
        $this->enterSchool($school);

        $options ??= [
            ['text' => 'A', 'correct' => true],
            ['text' => 'B', 'correct' => false],
            ['text' => 'C', 'correct' => false],
        ];

        $question = new Question(array_merge([
            'subject_id' => $subject->id,
            'question_text' => 'Sample question?',
            'marks' => 1,
        ], array_intersect_key($overrides, array_flip(['academic_level_id', 'level_arm_id', 'topic']))));
        $question->type = $type->value;
        $question->difficulty = $overrides['difficulty'] ?? 'medium';
        $question->status = $overrides['status'] ?? 'active';
        $question->save();

        foreach ($options as $position => $option) {
            $question->options()->create([
                'option_text' => $option['text'],
                'is_correct' => $option['correct'],
                'position' => $position + 1,
            ]);
        }

        $question = $question->fresh('options');

        $this->app->forgetScopedInstances();

        return $question;
    }

    /**
     * An examination for the given class context, in the given status.
     */
    protected function examinationIn(School $school, array $context, array $overrides = []): Examination
    {
        $this->enterSchool($school);

        $examination = Examination::factory()->create(array_merge([
            'academic_session_id' => $context['session']->id,
            'academic_period_id' => $context['period']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
            'subject_id' => $context['subject']->id,
        ], $overrides));

        $this->app->forgetScopedInstances();

        return $examination;
    }
}
