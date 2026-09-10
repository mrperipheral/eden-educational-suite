<?php

namespace Tests\Feature\Attendance;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\AttendanceRegister;
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
 * Shared setup for the Attendance Management feature tests (M13).
 *
 * Roles, from the M4 bundles:
 *   - School Admin / Principal → `attendance.manage` (any class + reopen)
 *   - Teacher                  → `attendance.record` (only an assigned class)
 *   - Staff                    → `attendance.view` only
 *   - Bursar / Parent / Student / role-less → 403
 *
 * The Attendance module is **on by default**; {@see self::disableAttendance()}
 * turns it off. Attendance does **not** depend on the Timetable module.
 */
abstract class AttendanceTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected string $attendanceDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->attendanceDate = now()->subDay()->toDateString();
    }

    /**
     * @return array{manage: list<Role>, record: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function attendanceRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'record' => [Role::Teacher],
            'view' => [Role::Staff],
            'denied' => [Role::Bursar, Role::Parent, Role::Student, null],
        ];
    }

    protected function disableAttendance(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'attendance', 'enabled' => false]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A current session (spanning today), a term, a level and an arm.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm}
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

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm');
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

    /** A draft register for the scaffold's class + date, with no records yet. */
    protected function registerFor(School $school, array $scaffold, array $attributes = []): AttendanceRegister
    {
        $this->enterSchool($school);

        $register = AttendanceRegister::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'attendance_date' => $this->attendanceDate,
        ], $attributes));

        $this->app->forgetScopedInstances();

        return $register;
    }

    /** Materialise one unmarked record per eligible student (mimics the store snapshot). */
    protected function snapshotRoster(School $school, AttendanceRegister $register): void
    {
        $this->enterSchool($school);
        $register->eligibleStudents()->get()->each(fn (Student $s) => $register->records()->firstOrCreate(['student_id' => $s->id]));
        $this->app->forgetScopedInstances();
    }

    /** @return array<string, mixed> */
    protected function registerPayload(array $scaffold, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'attendance_date' => $this->attendanceDate,
        ], $overrides);
    }

    /**
     * Create a Teacher-role user with a linked teacher record and an active
     * assignment for the scaffold's class, authenticate as them, and pin the
     * school context.
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
            'subject_id' => Subject::factory()->create()->id,
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ], $assignmentOverrides));

        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }
}
