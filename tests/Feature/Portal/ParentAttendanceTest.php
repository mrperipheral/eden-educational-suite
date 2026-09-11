<?php

namespace Tests\Feature\Portal;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRegister;
use App\Models\School;
use App\Models\SchoolModule;
use Illuminate\Support\Collection;

/**
 * Attendance visibility for the Parent Portal — only the signed-in parent's
 * own child's data, reusing M13's submitted registers (see
 * `docs/parent-portal.md` §"Attendance").
 */
class ParentAttendanceTest extends ParentPortalTestCase
{
    private function submittedRegister(School $school, array $scaffold, Collection $students, array $statuses): AttendanceRegister
    {
        $this->enterSchool($school);
        $admin = $this->adminUser($school);

        $register = AttendanceRegister::create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'attendance_date' => now()->subDays(2)->toDateString(),
        ]);
        $students->values()->each(fn ($student, $i) => $register->records()->create([
            'student_id' => $student->id,
            'status' => $statuses[$i % count($statuses)]->value,
        ]));
        $register->submit($admin);

        $this->app->forgetScopedInstances();

        return $register;
    }

    public function test_only_the_childs_own_attendance_is_visible(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        $this->submittedRegister($school, $scaffold, $students, [AttendanceStatus::Present]);

        $this->actingAsParent($school, $user);
        $response = $this->get("/parent/children/{$students[0]->id}/attendance")->assertOk();
        $response->assertSee('1');
    }

    public function test_a_siblings_attendance_never_leaks_into_another_childs_page(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 2);
        $scaffold = $this->scaffold($school);
        $this->enrollInScaffold($school, $scaffold, $students);
        // Child 0 present, child 1 absent — distinct outcomes.
        $this->submittedRegister($school, $scaffold, $students, [AttendanceStatus::Present, AttendanceStatus::Absent]);

        $this->actingAsParent($school, $user);

        $child0 = $this->get("/parent/children/{$students[0]->id}/attendance")->assertOk();
        $child0->assertSee('100%'); // 1 present / 1 opened = 100%

        $child1 = $this->get("/parent/children/{$students[1]->id}/attendance")->assertOk();
        $child1->assertDontSee('100%'); // 0 present / 1 opened = 0%, never child0's 100%
    }

    public function test_no_attendance_data_yet_shows_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/attendance")
            ->assertOk()
            ->assertSee(__('No attendance information is available yet'));
    }

    public function test_attendance_degrades_gracefully_when_the_module_is_off(): void
    {
        $school = $this->newSchool();
        [$user, , $students] = $this->parentWithChildren($school, 1);
        $this->enterSchool($school);
        SchoolModule::query()->create(['module' => 'attendance', 'enabled' => false]);
        $this->app->forgetScopedInstances();

        $this->actingAsParent($school, $user);
        $this->get("/parent/children/{$students[0]->id}/attendance")
            ->assertOk()
            ->assertSee(__('Attendance is not currently available'));
    }
}
