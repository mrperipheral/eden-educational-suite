<?php

namespace Tests\Feature\Results;

use App\Enums\AssessmentStatus;
use App\Enums\ResultRunStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;

/**
 * The result compiler (see `docs/results-report-cards.md` §"Result
 * compilation" / §"Missing scores" / §"Class position"):
 *   - only LOCKED, academic-purpose M14 assessments ever contribute;
 *   - a missing score blocks compilation entirely — nothing is manufactured;
 *   - the weighted percentage and grade are computed from the active
 *     weighting / grading scheme;
 *   - class position is a competition rank within the run's own roster.
 */
class ResultCompilationTest extends ResultsTestCase
{
    public function test_only_locked_assessments_are_included(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [8, 6]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15, 13]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/results/runs/{$run->id}/compile")->assertSessionHasNoErrors();

        $this->assertSame(ResultRunStatus::Compiled, $run->fresh()->status);
        $this->assertSame(2, $run->studentResults()->count());
        $this->assertSame(2, $run->subjectResults()->count());
    }

    public function test_a_draft_assessment_does_not_block_or_count(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [8, 6]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15, 13]);
        // A subject that only ever reached draft — never entered the run at all.
        $this->enterSchool($school);
        Assessment::factory()->create([
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject2']->id, 'assessment_category_id' => $scaffold['classwork']->id,
        ]);
        $this->app->forgetScopedInstances();
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->post("/results/runs/{$run->id}/compile")->assertSessionHasNoErrors();

        $this->assertSame(1, $run->subjectResults()->distinct('subject_id')->count('subject_id'));
    }

    public function test_a_published_but_unlocked_assessment_blocks_compilation_as_missing(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [8, 6]);
        // Exam exists and is scored, but only published — never locked.
        $this->enterSchool($school);
        $exam = Assessment::factory()->create([
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id, 'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id, 'assessment_category_id' => $scaffold['exam']->id,
        ]);
        $students->each(fn (Student $s) => $exam->scores()->create(['student_id' => $s->id, 'score' => 15]));
        $exam->publish();
        $this->app->forgetScopedInstances();
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/compile")
            ->assertSessionHasErrors('compilation');

        $this->assertSame(ResultRunStatus::Draft, $run->fresh()->status);
        $this->assertSame(0, $run->studentResults()->count());
    }

    public function test_an_unentered_score_blocks_compilation(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [8, null]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15, 13]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/compile")
            ->assertSessionHasErrors('compilation');

        $this->assertSame(0, $run->fresh()->studentResults()->count());
    }

    public function test_a_class_with_no_students_blocks_compilation(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);   // no students
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('results.runs.show', $run->id))->post("/results/runs/{$run->id}/compile")
            ->assertSessionHasErrors('compilation');
    }

    public function test_the_weighted_percentage_and_grade_are_calculated_correctly(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);   // Classwork 40% / Exam 60%
        $students = $this->enrolledStudents($school, $scaffold, 1);
        // max_score defaults to 20 in the helper.
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);   // 50%
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20]);        // 100%
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        // 50% * 40% + 100% * 60% = 20 + 60 = 80%.
        $subjectResult = StudentSubjectResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $this->assertSame('80.00', $subjectResult->percentage);
        $this->assertSame('A', $subjectResult->grade_code_snapshot);

        $overall = StudentResult::query()->where('result_run_id', $run->id)->firstOrFail();
        $this->assertSame('80.00', $overall->total_percentage);
        $this->assertSame('80.00', $overall->average_percentage);
        $this->assertSame(1, $overall->subject_count);
    }

    public function test_class_position_uses_competition_ranking_for_ties(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 4)->values();
        // Two students tie for first, one for third, one clear last.
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [20, 20, 10, 0]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20, 20, 10, 0]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        $positions = StudentResult::query()->where('result_run_id', $run->id)
            ->join('students', 'students.id', '=', 'student_results.student_id')
            ->orderBy('student_results.position')
            ->pluck('student_results.position')
            ->all();

        $this->assertSame([1, 1, 3, 4], $positions);
    }

    public function test_class_position_is_scoped_to_the_run_not_the_whole_school(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [20, 5]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20, 5]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        $this->assertSame(2, StudentResult::query()->where('result_run_id', $run->id)->max('class_size'));
        $this->assertEqualsCanonicalizing([1, 2], StudentResult::query()->where('result_run_id', $run->id)->pluck('position')->all());
    }

    public function test_ranking_disabled_means_no_position_is_calculated(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [20, 5]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [20, 5]);
        $run = $this->resultRun($school, $scaffold, ['ranking_enabled' => false]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        $this->assertTrue(StudentResult::query()->where('result_run_id', $run->id)->whereNull('position')->exists());
        $this->assertSame(0, StudentResult::query()->where('result_run_id', $run->id)->whereNotNull('position')->count());
    }

    public function test_recompiling_replaces_stale_results(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $classwork = $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [10]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $first = StudentSubjectResult::query()->where('result_run_id', $run->id)->value('percentage');

        // Unlock the classwork assessment and correct the score upward.
        $this->enterSchool($school);
        $classwork->status = AssessmentStatus::Published;
        $classwork->save();
        $classwork->scores()->update(['score' => 20]);
        $classwork->status = AssessmentStatus::Locked;
        $classwork->save();
        $this->app->forgetScopedInstances();

        $this->post("/results/runs/{$run->id}/compile")->assertSessionHasNoErrors();
        $second = StudentSubjectResult::query()->where('result_run_id', $run->id)->value('percentage');

        $this->assertNotSame($first, $second);
        $this->assertSame(1, StudentSubjectResult::query()->where('result_run_id', $run->id)->count(), 'recompiling does not duplicate rows');
    }

    public function test_no_cross_school_data_enters_a_compiled_run(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();
        $scaffoldA = $this->scaffold($a);
        $scaffoldB = $this->scaffold($b);
        $studentsA = $this->enrolledStudents($a, $scaffoldA, 1);
        $this->enrolledStudents($b, $scaffoldB, 3);
        $this->lockedAssessment($a, $scaffoldA, $scaffoldA['subject'], $scaffoldA['classwork'], $studentsA, [10]);
        $this->lockedAssessment($a, $scaffoldA, $scaffoldA['subject'], $scaffoldA['exam'], $studentsA, [10]);
        $run = $this->resultRun($a, $scaffoldA);
        $this->actingAsMemberOf($a, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");

        $this->assertSame(1, $run->studentResults()->count());
    }
}
