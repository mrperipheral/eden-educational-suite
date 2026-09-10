<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Enums\TimetableStatus;
use App\Models\Timetable;
use App\Support\Timetable\TimetableConflictScanner;

class TimetablePublishTest extends TimetableTestCase
{
    public function test_a_valid_draft_can_be_published(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $timetable->entries()->create($this->entryPayload($scaffold));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/timetables/{$timetable->id}/status", ['status' => 'published'])
            ->assertRedirect(route('timetables.show', $timetable->id))
            ->assertSessionHasNoErrors();

        $fresh = $timetable->fresh();
        $this->assertSame(TimetableStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_an_empty_timetable_cannot_be_published(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('timetables.show', $timetable->id))
            ->patch("/timetables/{$timetable->id}/status", ['status' => 'published'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TimetableStatus::Draft, $timetable->fresh()->status);
    }

    public function test_a_conflicting_timetable_cannot_be_published(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);

        // Two lessons for the same teacher + class that overlap — written
        // straight to the DB so no request-level validation runs.
        $this->enterSchool($school);
        $timetable->entries()->create($this->entryPayload($scaffold, ['start_time' => '08:00', 'end_time' => '09:00']));
        $timetable->entries()->create($this->entryPayload($scaffold, ['start_time' => '08:30', 'end_time' => '09:30']));
        $this->app->forgetScopedInstances();

        $this->enterSchool($school);
        $this->assertFalse(app(TimetableConflictScanner::class)->isPublishable($timetable));
        $this->assertCount(1, app(TimetableConflictScanner::class)->pairs($timetable));
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->from(route('timetables.show', $timetable->id))
            ->patch("/timetables/{$timetable->id}/status", ['status' => 'published'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TimetableStatus::Draft, $timetable->fresh()->status);
    }

    public function test_a_published_timetable_can_be_returned_to_draft(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold, ['status' => 'published', 'published_at' => now()]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/timetables/{$timetable->id}/status", ['status' => 'draft'])->assertRedirect();

        $fresh = $timetable->fresh();
        $this->assertSame(TimetableStatus::Draft, $fresh->status);
        $this->assertNull($fresh->published_at);
    }

    public function test_the_published_state_is_visible_on_the_list_and_page(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold, ['name' => 'Live Term', 'status' => 'published', 'published_at' => now()]);
        $this->actingAsMemberOf($school, Role::Teacher);

        $this->get('/timetables')->assertOk()->assertSee('Live Term')->assertSee('Published');
        $this->get("/timetables/{$timetable->id}")->assertOk()->assertSee('Published');
    }

    public function test_the_conflict_scanner_is_tenant_scoped(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $timetableA = $this->timetableFor($a, $scaffoldA);
        $this->enterSchool($a);
        $timetableA->entries()->create($this->entryPayload($scaffoldA));
        $this->app->forgetScopedInstances();

        // School B's context must not see School A's timetable at all.
        $this->enterSchool($b);
        $this->assertTrue(Timetable::query()->whereKey($timetableA->id)->doesntExist());
    }
}
