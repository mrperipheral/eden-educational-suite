<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Enums\TimetableStatus;
use App\Models\AcademicSession;
use App\Models\Timetable;
use App\Support\Tenancy\Exceptions\TenantMismatchException;

class TimetableTest extends TimetableTestCase
{
    private function rowsFor(int $schoolId)
    {
        return Timetable::query()->withoutGlobalScopes()->where('school_id', $schoolId);
    }

    public function test_admin_can_create_a_timetable(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post('/timetables', [
            'name' => 'Term 1',
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
        ])->assertRedirect();

        $timetable = $this->rowsFor($school->id)->firstOrFail();
        $this->assertSame('Term 1', $timetable->name);
        $this->assertSame($school->id, $timetable->school_id);
        $this->assertSame(TimetableStatus::Draft, $timetable->status, 'new timetables start as drafts');
    }

    public function test_creation_requires_a_name_and_a_valid_session(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/timetables/create')->post('/timetables', ['name' => '', 'academic_session_id' => 999999])
            ->assertSessionHasErrors(['name', 'academic_session_id']);

        $this->assertSame(0, $this->rowsFor($school->id)->count());
    }

    public function test_a_period_from_another_session_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $otherPeriod = $otherSession->periods()->create(['name' => 'X', 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-01', 'position' => 1]);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from('/timetables/create')->post('/timetables', [
            'name' => 'Bad', 'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $otherPeriod->id,
        ])->assertSessionHasErrors('academic_period_id');
    }

    public function test_admin_can_edit_the_name_and_period_but_not_the_session(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold, ['name' => 'Old']);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        $otherSession = AcademicSession::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/timetables/{$timetable->id}", [
            'name' => 'New', 'academic_period_id' => null, 'academic_session_id' => $otherSession->id,
        ])->assertRedirect(route('timetables.show', $timetable->id));

        $fresh = $timetable->fresh();
        $this->assertSame('New', $fresh->name);
        $this->assertNull($fresh->academic_period_id);
        $this->assertSame($scaffold['session']->id, $fresh->academic_session_id, 'the session is immutable after creation');
    }

    public function test_a_draft_timetable_can_be_deleted_but_a_published_one_cannot(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $published = $this->timetableFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $draft = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.show', $published->id))->delete("/timetables/{$published->id}")
            ->assertRedirect(route('timetables.show', $published->id))
            ->assertSessionHas('error');
        $this->assertNotNull($published->fresh());

        $this->delete("/timetables/{$draft->id}")->assertRedirect(route('timetables.index'));
        $this->assertNull($draft->fresh());
    }

    public function test_list_filters_by_session_and_status_and_paginates(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        Timetable::factory()->count(25)->create(['academic_session_id' => $scaffold['session']->id]);
        Timetable::factory()->published()->create(['academic_session_id' => $scaffold['session']->id, 'name' => 'Live One']);
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/timetables')->assertOk()
            ->assertViewHas('timetables', fn ($p) => $p->perPage() === 20 && $p->total() === 26);

        $this->get('/timetables?status=published')->assertOk()->assertSee('Live One');
    }

    // -- Authorization -------------------------------------------------

    public function test_view_roles_can_read_but_not_manage(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);

        foreach ($this->timetableRoles()['view'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/timetables')->assertOk();
            $this->get("/timetables/{$timetable->id}")->assertOk();
            $this->get('/timetables/create')->assertForbidden();
            $this->from('/timetables')->post('/timetables', ['name' => 'Nope', 'academic_session_id' => $scaffold['session']->id])->assertForbidden();
            $this->patch("/timetables/{$timetable->id}/status", ['status' => 'published'])->assertForbidden();
            $this->delete("/timetables/{$timetable->id}")->assertForbidden();
            $this->flushSession();
        }

        $this->assertSame(1, $this->rowsFor($school->id)->count());
    }

    public function test_denied_roles_get_403(): void
    {
        $school = $this->newSchool();
        $this->scaffold($school);

        foreach ($this->timetableRoles()['denied'] as $role) {
            $this->actingAsMemberOf($school, $role);
            $this->get('/timetables')->assertForbidden();
            $this->flushSession();
        }
    }

    // -- Module activation ------------------------------------------

    public function test_routes_are_unavailable_when_the_timetable_module_is_off(): void
    {
        // scaffold() enables it — this test deliberately does not.
        $school = $this->newSchool();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->get('/timetables')->assertNotFound();
        $this->get('/timetables/create')->assertNotFound();
        $this->post('/timetables', ['name' => 'x'])->assertNotFound();
    }

    public function test_module_gate_does_not_grant_permission(): void
    {
        $school = $this->newSchool();
        $this->enableTimetable($school);
        // Bursar has academic-less access — no timetable permission even with the module on.
        $this->actingAsMemberOf($school, Role::Bursar);
        $this->get('/timetables')->assertForbidden();
    }

    // -- Tenant isolation -----------------------------------------

    public function test_timetables_are_tenant_isolated_and_routes_resolve_safely(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $timetableA = $this->timetableFor($a, $scaffoldA, ['name' => 'Secret A']);

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get('/timetables')->assertOk()->assertDontSee('Secret A');
        $this->get("/timetables/{$timetableA->id}")->assertNotFound();
        $this->get("/timetables/{$timetableA->id}/edit")->assertNotFound();
        $this->patch("/timetables/{$timetableA->id}", ['name' => 'hax'])->assertNotFound();
        $this->patch("/timetables/{$timetableA->id}/status", ['status' => 'published'])->assertNotFound();
        $this->delete("/timetables/{$timetableA->id}")->assertNotFound();

        $this->assertSame('Secret A', $timetableA->fresh()->name);
    }

    public function test_a_cross_school_session_cannot_be_used(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->scaffold($b);
        $this->actingAsMemberOf($b, Role::SchoolAdmin);

        $this->from('/timetables/create')->post('/timetables', [
            'name' => 'x', 'academic_session_id' => $scaffoldA['session']->id,
        ])->assertSessionHasErrors('academic_session_id');

        $this->assertSame(0, $this->rowsFor($a->id)->count());
        $this->assertSame(0, $this->rowsFor($b->id)->count());
    }

    public function test_school_ownership_is_immutable_and_not_spoofable(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);

        $this->post('/timetables', [
            'name' => 'x', 'academic_session_id' => $scaffoldA['session']->id, 'school_id' => $b->id,
        ])->assertRedirect();
        $this->assertSame($a->id, $this->rowsFor($a->id)->firstOrFail()->school_id);
        $this->assertSame(0, $this->rowsFor($b->id)->count());

        $this->enterSchool($a);
        $timetable = Timetable::factory()->create(['academic_session_id' => $scaffoldA['session']->id]);
        $this->expectException(TenantMismatchException::class);
        $timetable->school_id = $b->id;
        $timetable->save();
    }
}
