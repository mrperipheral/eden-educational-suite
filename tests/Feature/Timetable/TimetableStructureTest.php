<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Enums\Weekday;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The clean domain boundaries M12 preserves:
 *   School → Timetables · Timetable → Entries ·
 *   Entry → Timetable / Level / Arm / Subject / Teacher
 * and nothing else (no attendance / result / student links on the timetable).
 */
class TimetableStructureTest extends TimetableTestCase
{
    public function test_school_owns_timetables_and_timetables_own_entries(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);

        $timetable = Timetable::factory()->create(['academic_session_id' => $scaffold['session']->id]);
        $timetable->entries()->create($this->entryPayload($scaffold));
        $timetable->entries()->create($this->entryPayload($scaffold, ['weekday' => Weekday::Tuesday->value]));

        $this->assertSame(1, $school->timetables()->count());
        $this->assertSame(2, $timetable->entries()->count());
        $this->assertSame(2, TimetableEntry::query()->count());

        $entry = TimetableEntry::query()->with(['timetable', 'level', 'arm', 'subject', 'teacher'])->first();
        $this->assertTrue($entry->timetable->is($timetable));
        $this->assertTrue($entry->arm->is($scaffold['arm']));
        $this->assertTrue($entry->subject->is($scaffold['subject']));
        $this->assertTrue($entry->teacher->is($scaffold['teacher']));
    }

    public function test_the_entry_table_carries_only_the_scheduling_columns(): void
    {
        $columns = Schema::getColumnListing('timetable_entries');
        sort($columns);

        $this->assertSame([
            'academic_level_id', 'created_at', 'end_time', 'id', 'level_arm_id', 'room',
            'school_id', 'start_time', 'subject_id', 'teacher_id', 'timetable_id', 'updated_at', 'weekday',
        ], $columns);

        // Session / period live on the parent timetable, never on the entry.
        $this->assertFalse(Schema::hasColumn('timetable_entries', 'academic_session_id'));
        $this->assertFalse(Schema::hasColumn('timetable_entries', 'academic_period_id'));
    }

    public function test_timetable_model_exposes_no_future_module_relationships(): void
    {
        $timetable = new Timetable;

        foreach (['students', 'attendance', 'results', 'marks', 'enrollments', 'lessons'] as $relation) {
            $this->assertFalse(method_exists($timetable, $relation), "Timetable should not define `{$relation}()`");
        }
    }

    public function test_deleting_a_timetable_cascades_only_its_entries(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $timetable->entries()->create($this->entryPayload($scaffold));

        Timetable::withoutGlobalScopes()->whereKey($timetable->id)->delete();

        $this->assertSame(0, TimetableEntry::query()->withoutGlobalScopes()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('subjects', ['id' => $scaffold['subject']->id]);
        $this->assertDatabaseHas('teachers', ['id' => $scaffold['teacher']->id]);
        $this->assertDatabaseHas('academic_levels', ['id' => $scaffold['level']->id]);
        $this->assertDatabaseHas('teacher_assignments', ['id' => $scaffold['assignment']->id]);
    }

    public function test_the_clashing_scope_treats_time_as_half_open(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        $timetable->entries()->create($this->entryPayload($scaffold, ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '10:00']));

        // Adjacent — must NOT be reported as clashing.
        $this->assertSame(0, TimetableEntry::query()->clashingWith($timetable->id, 1, '10:00', '11:00')->count());
        $this->assertSame(0, TimetableEntry::query()->clashingWith($timetable->id, 1, '08:00', '09:00')->count());
        // Overlapping — must be.
        $this->assertSame(1, TimetableEntry::query()->clashingWith($timetable->id, 1, '09:30', '10:30')->count());
        // Different weekday — must not.
        $this->assertSame(0, TimetableEntry::query()->clashingWith($timetable->id, 2, '09:00', '10:00')->count());
    }

    public function test_the_timetable_grid_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        collect(range(0, 9))->each(fn ($i) => $timetable->entries()->create($this->entryPayload($scaffold, [
            'weekday' => ($i % 5) + 1,
            'start_time' => sprintf('%02d:00', 8 + intdiv($i, 5)),
            'end_time' => sprintf('%02d:00', 9 + intdiv($i, 5)),
        ])));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/timetables/{$timetable->id}")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "timetable grid ran {$queries} queries for 10 lessons");
    }

    public function test_the_teacher_view_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        collect(range(1, 6))->each(fn ($i) => $timetable->entries()->create($this->entryPayload($scaffold, ['weekday' => $i])));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/timetables/teacher-view?teacher='.$scaffold['teacher']->id)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "teacher view ran {$queries} queries for 6 lessons");
    }

    public function test_overlap_checks_are_bounded_when_adding_to_a_busy_timetable(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $timetable = $this->timetableFor($school, $scaffold);
        $this->enterSchool($school);
        // 20 non-clashing lessons already on the board.
        collect(range(0, 19))->each(fn ($i) => $timetable->entries()->create($this->entryPayload($scaffold, [
            'weekday' => ($i % 5) + 1,
            'start_time' => sprintf('%02d:00', 8 + ($i % 4)),
            'end_time' => sprintf('%02d:00', 9 + ($i % 4)),
        ])));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->post("/timetables/{$timetable->id}/entries", $this->entryPayload($scaffold, [
            'weekday' => 6, 'start_time' => '12:00', 'end_time' => '13:00',
        ]))->assertSessionHasNoErrors();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // A handful of existence queries + the insert — never one-per-existing-lesson.
        $this->assertLessThan(20, $queries, "adding a lesson ran {$queries} queries against a 20-lesson board");
    }
}
