<?php

namespace Tests\Feature\Teacher;

use App\Enums\Role;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The clean domain boundaries M11 preserves:
 *   School → Teachers · Teacher → (optional User, TeacherAssignments) ·
 *   TeacherAssignment → Teacher / Session / Period / Level / Arm / Subject
 * and nothing else (no attendance / result / fee / guardian / timetable links).
 */
class TeacherStructureTest extends TeacherTestCase
{
    public function test_school_owns_teachers_and_teachers_own_assignments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);

        $teachers = Teacher::factory()->count(3)->create();
        $teachers->each(fn (Teacher $t) => $t->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => '2025-09-15',
        ]));

        $this->assertSame(3, $school->teachers()->count());
        $this->assertSame(1, $teachers->first()->assignments()->count());
        $this->assertSame(3, TeacherAssignment::query()->count());
        $this->assertNull($teachers->first()->user, 'a teacher is not a login by default');
    }

    public function test_the_assignment_table_carries_only_the_foundation_columns(): void
    {
        $columns = Schema::getColumnListing('teacher_assignments');

        sort($columns);
        $this->assertSame([
            'academic_level_id', 'academic_period_id', 'academic_session_id', 'created_at', 'ended_on',
            'id', 'level_arm_id', 'school_id', 'started_on', 'status', 'subject_id', 'teacher_id', 'updated_at',
        ], $columns);
    }

    public function test_teacher_model_exposes_no_future_module_relationships(): void
    {
        $teacher = new Teacher;

        foreach (['attendance', 'results', 'marks', 'fees', 'invoices', 'payments', 'guardians', 'timetable', 'timetableSlots'] as $relation) {
            $this->assertFalse(method_exists($teacher, $relation), "Teacher should not define `{$relation}()`");
        }
    }

    public function test_active_assignments_relationship_is_the_open_ones(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);

        $open = $teacher->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => '2025-09-15',
        ]);
        $closed = $teacher->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'status' => 'ended',
            'started_on' => '2024-09-15',
            'ended_on' => '2025-07-24',
        ]);

        $this->assertSame([$open->id], $teacher->activeAssignments()->pluck('id')->all());
        $this->assertSame(2, $teacher->assignments()->count());
    }

    public function test_deleting_a_teacher_cascades_only_its_assignments(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $teacher = $this->teacherFor($school);
        $this->enterSchool($school);
        $teacher->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => '2025-09-15',
        ]);

        Teacher::withoutGlobalScopes()->whereKey($teacher->id)->delete();

        $this->assertSame(0, TeacherAssignment::query()->withoutGlobalScopes()->where('school_id', $school->id)->count());
        $this->assertDatabaseHas('subjects', ['id' => $scaffold['subject']->id]);
        $this->assertDatabaseHas('academic_levels', ['id' => $scaffold['level']->id]);
    }

    public function test_the_teacher_list_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        Teacher::factory()->count(12)->create()->each(fn (Teacher $t) => $t->assignments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'subject_id' => $scaffold['subject']->id,
            'started_on' => '2025-09-15',
        ]));
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/teachers')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries, "teacher list ran {$queries} queries for 12 teachers");
    }
}
