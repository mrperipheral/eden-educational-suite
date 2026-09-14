<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * M29.5 — the "Your classes today" dashboard widget (Teacher via the shared
 * `DashboardController`, Student via `StudentPortalController`). Both reuse
 * the existing Timetable module's data — see `docs/ui-ux-guidelines.md`
 * §"Dashboards". These tests exist because a real bug shipped past the rest
 * of the suite: eager-loading a `Teacher`/`Student` with a trimmed column
 * list (`id,first_name,last_name`) then calling `fullName()` (which also
 * reads `middle_name`) threw `MissingAttributeException` — caught only by
 * actually rendering the page with a teacher/student that HAS a middle name.
 */
class DashboardTodayClassesTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // A fixed Monday so "today's classes" is deterministic regardless
        // of the real calendar date the suite happens to run on.
        Carbon::setTestNow(Carbon::parse('2026-03-02')); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{level: AcademicLevel, arm: LevelArm, subject: Subject, teacher: Teacher, session: AcademicSession, period: AcademicPeriod}
     */
    private function scaffoldTodaysLesson(School $school, array $teacherAttributes = []): array
    {
        $this->enterSchool($school);
        SchoolModule::query()->firstOrCreate(['module' => 'timetable'], ['enabled' => true]);

        $session = AcademicSession::factory()->current()->create();
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => '2026-01-05', 'ends_on' => '2026-04-03', 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $subject = Subject::factory()->create();

        // A teacher WITH a middle name — this is exactly the case the bug
        // dropped when the eager-load select list omitted it.
        $teacher = Teacher::factory()->create(array_merge(['middle_name' => 'Sonia'], $teacherAttributes));

        $timetable = Timetable::factory()->published()->create([
            'academic_session_id' => $session->id,
            'academic_period_id' => $period->id,
        ]);

        $timetable->entries()->create([
            'academic_level_id' => $level->id,
            'level_arm_id' => $arm->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'weekday' => Carbon::now()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        $this->app->forgetScopedInstances();

        return compact('level', 'arm', 'subject', 'teacher', 'session', 'period');
    }

    public function test_a_teacher_sees_their_own_class_today_on_the_shared_dashboard(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffoldTodaysLesson($school);

        $this->enterSchool($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Teacher);
        $scaffold['teacher']->user_id = $user->id;
        $scaffold['teacher']->save();
        $this->app->forgetScopedInstances();

        $response = $this->actingAs($user)->withSession([
            EnforceTenant::SESSION_KEY => $school->getKey(),
        ])->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Your classes today');
        $response->assertSee($scaffold['subject']->name);
    }

    public function test_a_student_sees_their_own_class_today_including_the_teachers_full_name(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffoldTodaysLesson($school);

        $this->enterSchool($school);
        SchoolModule::query()->firstOrCreate(['module' => 'student-portal'], ['enabled' => true]);
        $studentUser = User::factory()->create();
        $studentUser->joinSchool($school, Role::Student);
        $student = Student::factory()->create();
        $student->user_id = $studentUser->id;
        $student->save();

        $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'started_on' => '2026-01-05',
        ]);
        $this->app->forgetScopedInstances();

        $response = $this->actingAs($studentUser)->withSession([
            EnforceTenant::SESSION_KEY => $school->getKey(),
        ])->get('/student');

        $response->assertOk();
        $response->assertSee('Your classes today');
        $response->assertSee($scaffold['subject']->name);
        // The teacher's full name (first + middle + last) must render
        // without a MissingAttributeException — the exact regression.
        $response->assertSee($scaffold['teacher']->fullName());
    }
}
