<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\AttendanceRegister;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class AttendanceRegisterTest extends AttendanceTestCase
{
    private function rowsFor(int $schoolId)
    {
        return AttendanceRegister::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_a_register_is_created_and_the_roster_is_snapshotted(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 4);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/attendance', $this->registerPayload($scaffold))
            ->assertRedirect();

        $register = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame($school->id, $register->school_id);
        $this->assertSame(AttendanceRegisterStatus::Draft, $register->status);
        $this->assertSame(4, $register->records()->count(), 'one record per eligible student');
        $this->assertSame(4, $register->records()->whereNull('status')->count(), 'marks start unmarked');
    }

    public function test_required_fields_and_a_future_date_are_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/attendance/create')->post('/attendance', [
            'academic_session_id' => '', 'academic_level_id' => '', 'level_arm_id' => '',
            'attendance_date' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors(['academic_session_id', 'academic_level_id', 'level_arm_id', 'attendance_date']);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_date_outside_the_session_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/attendance/create')
            ->post('/attendance', $this->registerPayload($scaffold, [
                'attendance_date' => now()->subYears(2)->toDateString(),
            ]))
            ->assertSessionHasErrors('attendance_date');
    }

    public function test_a_period_from_another_session_and_an_arm_from_another_level_are_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2020-01-01', 'ends_on' => '2020-04-01', 'position' => 1]);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Blue', 'code' => 'B', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/attendance/create')
            ->post('/attendance', $this->registerPayload($scaffold, ['academic_period_id' => $otherPeriod->id]))
            ->assertSessionHasErrors('academic_period_id');

        $this->from('/attendance/create')
            ->post('/attendance', $this->registerPayload($scaffold, ['level_arm_id' => $otherArm->id]))
            ->assertSessionHasErrors('level_arm_id');
    }

    public function test_a_class_with_no_enrolled_students_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);   // no students enrolled
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/attendance/create')
            ->post('/attendance', $this->registerPayload($scaffold))
            ->assertSessionHasErrors('level_arm_id');

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_second_register_for_the_same_class_and_date_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/attendance', $this->registerPayload($scaffold))->assertSessionHasNoErrors();
        $this->from('/attendance/create')->post('/attendance', $this->registerPayload($scaffold))
            ->assertSessionHasErrors('attendance_date');

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_the_same_class_can_have_a_register_on_a_different_day(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/attendance', $this->registerPayload($scaffold, ['attendance_date' => now()->subDays(1)->toDateString()]))->assertSessionHasNoErrors();
        $this->post('/attendance', $this->registerPayload($scaffold, ['attendance_date' => now()->subDays(2)->toDateString()]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->rowsFor($school->id)->count());
    }

    public function test_the_list_filters_by_date_class_and_status(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        $this->registerFor($school, $scaffold, ['attendance_date' => now()->subDays(1)->toDateString()]);
        $this->registerFor($school, $scaffold, ['attendance_date' => now()->subDays(2)->toDateString(), 'status' => 'submitted', 'submitted_at' => now()]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/attendance')->assertOk()
            ->assertViewHas('registers', fn ($p) => $p->total() === 2 && $p->perPage() === 20);
        $this->get('/attendance?status=submitted')->assertOk()
            ->assertViewHas('registers', fn ($p) => $p->total() === 1);
        $this->get('/attendance?date='.now()->subDays(1)->toDateString())->assertOk()
            ->assertViewHas('registers', fn ($p) => $p->total() === 1);
    }

    // -- Tenant isolation ---------------------------------------------

    public function test_registers_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $registerA = $this->registerFor($a, $scaffoldA);
        $this->snapshotRoster($a, $registerA);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/attendance')->assertOk()->assertViewHas('registers', fn ($p) => $p->total() === 0);
        $this->get("/attendance/{$registerA->id}")->assertNotFound();
        $this->patch("/attendance/{$registerA->id}/records", ['records' => []])->assertNotFound();
        $this->post("/attendance/{$registerA->id}/submit")->assertNotFound();
        $this->post("/attendance/{$registerA->id}/reopen")->assertNotFound();
        $this->delete("/attendance/{$registerA->id}")->assertNotFound();

        $this->assertSame(AttendanceRegisterStatus::Draft, $registerA->fresh()->status);
    }

    public function test_a_register_cannot_be_created_with_another_schools_class(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $this->enrolledStudents($a, $scaffoldA);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from('/attendance/create')->post('/attendance', [
            'academic_session_id' => $scaffoldA['session']->id,
            'academic_period_id' => $scaffoldA['period']->id,
            'academic_level_id' => $scaffoldA['level']->id,
            'level_arm_id' => $scaffoldA['arm']->id,
            'attendance_date' => $this->attendanceDate,
        ])->assertSessionHasErrors(['academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id']);

        $this->assertSame(0, $this->rowsFor($a->id)->count());
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_school_ownership_is_immutable_and_not_spoofable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/attendance', $this->registerPayload($scaffoldA, ['school_id' => $b->id]))->assertRedirect();
        $register = $this->rowsFor($a->id)->firstOrFail();
        $this->assertSame($a->id, $register->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());

        $this->expectException(TenantMismatchException::class);
        $register->school_id = $b->id;
        $register->save();
    }

    public function test_a_record_cannot_be_stamped_for_another_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->enrolledStudents($a, $scaffoldA);
        $registerA = $this->registerFor($a, $scaffoldA);
        $this->snapshotRoster($a, $registerA);
        $this->enterSchool($a);
        $record = $registerA->records()->first();

        $this->expectException(TenantMismatchException::class);
        $record->school_id = $b->id;
        $record->save();
    }
}
