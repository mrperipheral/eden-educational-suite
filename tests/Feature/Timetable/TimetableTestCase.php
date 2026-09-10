<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\School;
use App\Models\SchoolModule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\Timetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Shared setup for the Timetable Management feature tests (M12).
 *
 * Roles, from the M4 bundles (same shape as the Academic area):
 *   - School Admin / Principal → `timetable.manage` (+ view)
 *   - Teacher / Staff          → `timetable.view` only
 *   - Bursar / Parent / Student / role-less → 403
 *
 * The Timetable module is **off by default** — {@see self::scaffold()} turns it
 * on (and builds the academic structure + a backing teacher assignment).
 */
abstract class TimetableTestCase extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array{manage: list<Role>, view: list<Role>, denied: list<Role|null>}
     */
    protected function timetableRoles(): array
    {
        return [
            'manage' => [Role::SchoolAdmin, Role::Principal],
            'view' => [Role::Teacher, Role::Staff],
            'denied' => [Role::Bursar, Role::Parent, Role::Student, null],
        ];
    }

    /** Turn the Timetable module on for a school (it is off by default). */
    protected function enableTimetable(School $school): void
    {
        $this->enterSchool($school);
        SchoolModule::query()->firstOrCreate(['module' => 'timetable'], ['enabled' => true]);
        $this->app->forgetScopedInstances();
    }

    /**
     * A school with the Timetable module on, a current session + term, a level +
     * arm, a subject offered by that level, and a teacher with an active
     * assignment to teach that subject to that level — so a lesson can validate.
     *
     * @return array{session: AcademicSession, period: AcademicPeriod, level: AcademicLevel, arm: LevelArm, subject: Subject, teacher: Teacher, assignment: TeacherAssignment}
     */
    protected function scaffold(School $school): array
    {
        $this->enterSchool($school);
        SchoolModule::query()->firstOrCreate(['module' => 'timetable'], ['enabled' => true]);

        $session = AcademicSession::factory()->current()->create();
        $period = $session->periods()->create([
            'name' => 'First Term', 'starts_on' => '2025-09-15', 'ends_on' => '2025-12-12', 'position' => 1,
        ]);
        $level = AcademicLevel::factory()->create();
        $arm = $level->arms()->create(['name' => 'Gold', 'code' => 'G', 'position' => 1]);
        $subject = Subject::factory()->create();
        $level->subjects()->attach($subject->id, ['school_id' => $school->id]);

        $teacher = Teacher::factory()->create();
        $assignment = $teacher->assignments()->create([
            'academic_session_id' => $session->id,
            'academic_level_id' => $level->id,
            'subject_id' => $subject->id,
            'started_on' => '2025-09-15',
        ]);

        $this->app->forgetScopedInstances();

        return compact('session', 'period', 'level', 'arm', 'subject', 'teacher', 'assignment');
    }

    /** A timetable for the scaffold's session + term. */
    protected function timetableFor(School $school, array $scaffold, array $attributes = []): Timetable
    {
        $this->enterSchool($school);
        $timetable = Timetable::factory()->create(array_merge([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
        ], $attributes));
        $this->app->forgetScopedInstances();

        return $timetable;
    }

    /** @return array<string, mixed> */
    protected function entryPayload(array $s, array $overrides = []): array
    {
        return array_merge([
            'academic_level_id' => $s['level']->id,
            'level_arm_id' => $s['arm']->id,
            'subject_id' => $s['subject']->id,
            'teacher_id' => $s['teacher']->id,
            'weekday' => 1,          // Monday
            'start_time' => '08:00',
            'end_time' => '09:00',
        ], $overrides);
    }
}
