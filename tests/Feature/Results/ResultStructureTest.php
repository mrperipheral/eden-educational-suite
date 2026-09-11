<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\GradingScheme;
use App\Models\ResultWeightingScheme;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structural guarantees that don't fit neatly into the lifecycle/compilation/
 * report-card test files: the compiled tables carry only the columns M15
 * designed (no accidental derived-field creep), compiling and rendering stay
 * query-bounded as class size grows, and a locked run's numbers survive later
 * edits to the mutable config it was compiled from (grading scheme, weighting
 * scheme, assessment/category names, student status) — see
 * `docs/results-report-cards.md` §"Historical snapshots".
 */
class ResultStructureTest extends ResultsTestCase
{
    public function test_student_results_table_has_only_the_designed_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'school_id', 'result_run_id', 'student_id',
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id',
            'total_percentage', 'average_percentage',
            'class_teacher_comment', 'principal_comment',
            'subject_count', 'overall_grade_code_snapshot', 'overall_grade_remark_snapshot',
            'position', 'class_size',
            'days_school_opened', 'days_present', 'days_absent', 'attendance_percentage',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('student_results'));
    }

    public function test_student_subject_results_table_has_only_the_designed_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'school_id', 'result_run_id', 'student_id', 'subject_id',
            'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id',
            'percentage', 'grading_scheme_grade_id', 'grade_code_snapshot', 'grade_remark_snapshot',
            'subject_position', 'is_adjusted', 'adjusted_by', 'adjusted_at',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('student_subject_results'));
    }

    public function test_student_subject_result_components_table_has_only_the_designed_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'school_id', 'student_subject_result_id',
            'assessment_category_id', 'category_name_snapshot', 'weight_percentage_snapshot',
            'raw_score', 'raw_max_score', 'score_percentage', 'weighted_contribution', 'position',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('student_subject_result_components'));
    }

    public function test_a_components_raw_score_and_max_are_populated_from_the_locked_assessment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [14], ['max_score' => 20]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [9], ['max_score' => 10]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $components = $subjectResult->components()->orderBy('position')->get();

        $classworkComponent = $components->firstWhere('category_name_snapshot', 'Classwork');
        $examComponent = $components->firstWhere('category_name_snapshot', 'Exam');

        $this->assertSame('14.00', $classworkComponent->raw_score);
        $this->assertSame('20.00', $classworkComponent->raw_max_score);
        $this->assertSame('9.00', $examComponent->raw_score);
        $this->assertSame('10.00', $examComponent->raw_max_score);
    }

    public function test_compiling_a_run_uses_a_bounded_number_of_queries_regardless_of_class_size(): void
    {
        $small = $this->newSchool();
        $smallScaffold = $this->scaffold($small);
        $smallStudents = $this->enrolledStudents($small, $smallScaffold, 2);
        $this->lockedAssessment($small, $smallScaffold, $smallScaffold['subject'], $smallScaffold['classwork'], $smallStudents, [10, 15]);
        $this->lockedAssessment($small, $smallScaffold, $smallScaffold['subject'], $smallScaffold['exam'], $smallStudents, [10, 15]);
        $smallRun = $this->resultRun($small, $smallScaffold);

        $large = $this->newSchool();
        $largeScaffold = $this->scaffold($large);
        $largeStudents = $this->enrolledStudents($large, $largeScaffold, 12);
        $this->lockedAssessment($large, $largeScaffold, $largeScaffold['subject'], $largeScaffold['classwork'], $largeStudents, array_fill(0, 12, 10));
        $this->lockedAssessment($large, $largeScaffold, $largeScaffold['subject'], $largeScaffold['exam'], $largeStudents, array_fill(0, 12, 15));
        $largeRun = $this->resultRun($large, $largeScaffold);

        $this->actingAsMemberOf($small, Role::SchoolAdmin);
        $this->flushSession();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->post("/results/runs/{$smallRun->id}/compile")->assertSessionHasNoErrors();
        $smallQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(2, $smallRun->studentResults()->count());
        $this->flushSession();

        $this->actingAsMemberOf($large, Role::SchoolAdmin);
        $this->flushSession();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->post("/results/runs/{$largeRun->id}/compile")->assertSessionHasNoErrors();
        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(12, $largeRun->studentResults()->count());

        $this->assertSame(
            $smallQueryCount, $largeQueryCount,
            'compiling 12 students should issue exactly the same number of queries as compiling 2 — no per-student query.',
        );
    }

    public function test_the_run_index_and_show_pages_stay_query_bounded_as_students_grow(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 10);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, array_fill(0, 10, 10));
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, array_fill(0, 10, 15));
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->flushSession();

        DB::enableQueryLog();
        $this->get('/results/runs')->assertOk();
        $indexQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get("/results/runs/{$run->id}")->assertOk();
        $showQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $indexQueries, 'the run index should not issue a query per row.');
        $this->assertLessThan(20, $showQueries, 'the run detail page should not issue a query per student.');
    }

    public function test_report_card_rendering_stays_query_bounded_as_subjects_grow(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject2'], $scaffold['classwork'], $students, [12]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject2'], $scaffold['exam'], $students, [18]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $studentResult = StudentResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $this->flushSession();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        DB::enableQueryLog();
        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $run->subjectResults()->distinct('subject_id')->count('subject_id'));
        $this->assertLessThan(30, $queryCount, 'rendering a two-subject report card should not scale per subject.');
    }

    public function test_a_locked_runs_stored_grade_is_unaffected_by_a_later_grading_scheme_change(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);   // 50%
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);        // 100%
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        // 50% * 40% + 100% * 60% = 80% -> grade A under the scaffold's scheme.
        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $this->assertSame('A', $subjectResult->grade_code_snapshot);

        // Redefine the grading scheme after locking so 80% would now be a "C".
        $this->enterSchool($school);
        GradingScheme::find($scaffold['grading']->id)->grades()
            ->where('code', 'A')->update(['min_percentage' => 95]);
        $this->app->forgetScopedInstances();

        $this->assertSame(
            'A', $subjectResult->fresh()->grade_code_snapshot,
            'a locked result keeps its snapshotted grade even after the grading scheme changes.',
        );
    }

    public function test_a_locked_runs_stored_weights_are_unaffected_by_a_later_weighting_scheme_change(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $storedWeights = $subjectResult->components()->orderBy('position')->pluck('weight_percentage_snapshot')->all();
        $this->assertSame(['40.00', '60.00'], $storedWeights);

        // Rebalance the weighting scheme's items after locking.
        $this->enterSchool($school);
        ResultWeightingScheme::find($scaffold['weighting']->id)->items()
            ->where('assessment_category_id', $scaffold['classwork']->id)->update(['weight_percentage' => 10]);

        $this->assertSame(
            ['40.00', '60.00'],
            $subjectResult->components()->orderBy('position')->pluck('weight_percentage_snapshot')->all(),
            'a locked result keeps its snapshotted weights even after the weighting scheme changes.',
        );
        $this->assertSame('80.00', $subjectResult->fresh()->percentage, 'the stored percentage itself is untouched too.');
    }

    public function test_a_locked_runs_category_names_are_unaffected_by_a_later_category_rename(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();

        $this->enterSchool($school);
        $scaffold['classwork']->update(['name' => 'Continuous Assessment (renamed)']);

        $names = $subjectResult->components()->pluck('category_name_snapshot')->all();
        $this->assertContains('Classwork', $names);
        $this->assertNotContains('Continuous Assessment (renamed)', $names);
    }

    public function test_a_locked_runs_results_survive_a_students_status_changing(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        $studentResult = StudentResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $percentageBefore = $studentResult->average_percentage;

        $this->enterSchool($school);
        $students->first()->enrollments()->latest('id')->first()->delete();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")->assertOk();
        $this->assertSame($percentageBefore, $studentResult->fresh()->average_percentage);
    }
}
