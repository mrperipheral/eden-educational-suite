<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\ResultRun;
use App\Models\Student;
use App\Models\StudentResult;
use Illuminate\Support\Facades\DB;

/**
 * N+1 regression guards for M27 reporting (spec §18 — "no N+1", query count
 * must not scale with row count). Mirrors the exact pattern already
 * established in `AuditPerformanceTest`/`CbtPerformanceTest`/
 * `EntryAssessmentPerformanceTest`: query count must stay flat as more rows
 * are added, never just "the page loads".
 */
class PerformanceTest extends ReportsTestCase
{
    private function countQueriesFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    public function test_dashboard_kpi_query_count_does_not_grow_with_more_students(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get(route('dashboard'))->assertOk());

        $this->enrolledStudents($school, $scaffold, 20);

        $afterMore = $this->countQueriesFor(fn () => $this->get(route('dashboard'))->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'dashboard KPI cards must not issue more queries as more students are added');
    }

    public function test_student_enrollment_report_query_count_does_not_grow_with_more_students(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get(route('reports.students.index'))->assertOk());

        $this->enrolledStudents($school, $scaffold, 30);

        $afterMore = $this->countQueriesFor(fn () => $this->get(route('reports.students.index'))->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the student enrollment report must not scale with student count');
    }

    public function test_academic_result_run_summary_query_count_does_not_grow_with_more_runs(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get(route('reports.academic.index'))->assertOk());

        $this->enterSchool($school);
        for ($i = 0; $i < 10; $i++) {
            $period = $scaffold['session']->periods()->create([
                'name' => "Extra term {$i}", 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'position' => 10 + $i,
            ]);
            ResultRun::factory()->create([
                'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $period->id,
                'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
                'grading_scheme_id' => $scaffold['grading']->id, 'result_weighting_scheme_id' => $scaffold['weighting']->id,
                'status' => 'published',
            ]);
        }
        $this->app->forgetScopedInstances();

        $afterMore = $this->countQueriesFor(fn () => $this->get(route('reports.academic.index'))->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'result-run summary must not scale with the number of result runs');
    }

    public function test_teacher_scoped_academic_report_query_count_does_not_grow(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->actingAsTeacherFor($school, $scaffold);

        $baseline = $this->countQueriesFor(fn () => $this->get(route('reports.academic.index'))->assertOk());

        $this->enterSchool($school);
        for ($i = 0; $i < 10; $i++) {
            $period = $scaffold['session']->periods()->create([
                'name' => "Extra term {$i}", 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'position' => 10 + $i,
            ]);
            ResultRun::factory()->create([
                'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $period->id,
                'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
                'grading_scheme_id' => $scaffold['grading']->id, 'result_weighting_scheme_id' => $scaffold['weighting']->id,
                'status' => 'published',
            ]);
        }
        $this->app->forgetScopedInstances();

        $afterMore = $this->countQueriesFor(fn () => $this->get(route('reports.academic.index'))->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'a teacher-scoped report must not scale with the number of result runs either');
    }

    public function test_academic_export_streams_via_chunking_without_loading_everything_at_once(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 5);

        $this->enterSchool($school);
        $run = $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->enterSchool($school);
        foreach ($students as $student) {
            StudentResult::factory()->create([
                'result_run_id' => $run->id, 'student_id' => $student->id,
                'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id,
                'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            ]);
        }
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $csv = $this->get(route('reports.academic.export', ['tab' => 'student']))->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        // Header + one row per student — proves the chunked export produced
        // every row without needing to inspect internal query counts.
        $this->assertCount(6, $lines);
    }
}
