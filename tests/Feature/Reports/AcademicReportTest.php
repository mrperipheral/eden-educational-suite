<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\ResultRun;
use App\Models\StudentResult;
use App\Models\StudentSubjectResult;
use App\Models\Teacher;
use App\Reports\AcademicReport;

/**
 * `AcademicReport` (M27 §3) — reads already-compiled, frozen M15
 * `StudentResult`/`StudentSubjectResult` rows. Never recomputes a grade or
 * touches a draft/compiled (unlocked) run's numbers.
 */
class AcademicReportTest extends ReportsTestCase
{
    /** A second term, so a second run can exist for the same class without hitting the unique constraint. */
    private function secondPeriod(array $scaffold): AcademicPeriod
    {
        return $scaffold['session']->periods()->create([
            'name' => 'Second Term',
            'starts_on' => now()->addMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(5)->toDateString(),
            'position' => 2,
        ]);
    }

    public function test_result_run_summary_only_counts_result_runs_for_this_school(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $period2 = $this->secondPeriod($scaffold);
        $this->app->forgetScopedInstances();

        $this->resultRun($school, $scaffold, ['status' => 'published']);
        $this->resultRun($school, $scaffold, ['status' => 'locked', 'academic_period_id' => $period2->id]);

        $other = $this->newSchool();
        $otherScaffold = $this->scaffold($other);
        $this->resultRun($other, $otherScaffold, ['status' => 'published']);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(AcademicReport::class)->resultRunSummary([], $admin);
        $this->assertSame(2, $page->total());
    }

    public function test_student_performance_only_returns_published_or_locked_runs(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        $period2 = $this->secondPeriod($scaffold);
        $draftRun = $this->resultRun($school, $scaffold, ['status' => 'draft']);
        $publishedRun = $this->resultRun($school, $scaffold, ['status' => 'published', 'academic_period_id' => $period2->id]);

        $this->enterSchool($school);
        StudentResult::factory()->create([
            'result_run_id' => $draftRun->id, 'student_id' => $students[0]->id,
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
        ]);
        StudentResult::factory()->create([
            'result_run_id' => $publishedRun->id, 'student_id' => $students[1]->id,
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id, 'average_percentage' => 88,
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(AcademicReport::class)->studentPerformance([], $admin);

        $this->assertSame(1, $page->total());
        $this->assertSame(88.0, (float) $page->items()[0]->average_percentage);
    }

    public function test_subject_performance_aggregates_average_and_grade_distribution(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);

        $this->enterSchool($school);
        $run = $this->resultRun($school, $scaffold, ['status' => 'locked']);

        $this->enterSchool($school);
        StudentSubjectResult::factory()->create([
            'result_run_id' => $run->id, 'student_id' => $students[0]->id, 'subject_id' => $scaffold['subject']->id,
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id, 'percentage' => 90, 'grade_code_snapshot' => 'A',
        ]);
        StudentSubjectResult::factory()->create([
            'result_run_id' => $run->id, 'student_id' => $students[1]->id, 'subject_id' => $scaffold['subject']->id,
            'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id, 'percentage' => 60, 'grade_code_snapshot' => 'B',
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $rows = app(AcademicReport::class)->subjectPerformance([], $admin);

        $row = $rows->firstWhere('subject_id', $scaffold['subject']->id);
        $this->assertSame(2, $row['students_assessed']);
        $this->assertSame(75.0, $row['average_percentage']);
        $this->assertSame(['A' => 1, 'B' => 1], $row['grades']);
    }

    public function test_class_performance_groups_by_level_and_arm_with_names(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        $run = $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->enterSchool($school);
        foreach ($students as $student) {
            StudentResult::factory()->create([
                'result_run_id' => $run->id, 'student_id' => $student->id,
                'academic_session_id' => $scaffold['session']->id, 'academic_period_id' => $scaffold['period']->id, 'academic_level_id' => $scaffold['level']->id,
                'level_arm_id' => $scaffold['arm']->id, 'average_percentage' => 80,
                'overall_grade_code_snapshot' => 'A',
            ]);
        }
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $rows = app(AcademicReport::class)->classPerformance([], $admin);

        $this->assertCount(1, $rows);
        $this->assertSame($scaffold['level']->name, $rows[0]['level_name']);
        $this->assertSame($scaffold['arm']->name, $rows[0]['arm_name']);
        $this->assertSame(2, $rows[0]['student_count']);
        $this->assertSame(['A' => 2], $rows[0]['grades']);
    }

    public function test_teacher_without_manage_only_sees_their_own_assigned_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 1);

        $this->enterSchool($school);
        $ownRun = $this->resultRun($school, $scaffold, ['status' => 'published']);

        $this->enterSchool($school);
        $otherLevel = AcademicLevel::factory()->create();
        $otherArm = $otherLevel->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        ResultRun::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $otherLevel->id,
            'level_arm_id' => $otherArm->id,
            'grading_scheme_id' => $scaffold['grading']->id,
            'result_weighting_scheme_id' => $scaffold['weighting']->id,
            'status' => 'published',
        ]);
        $this->app->forgetScopedInstances();

        $teacher = $this->actingAsTeacherFor($school, $scaffold);
        $this->enterSchool($school);
        $page = app(AcademicReport::class)->resultRunSummary([], $teacher);

        $this->assertSame(1, $page->total());
        $this->assertSame($ownRun->id, $page->items()[0]->id);
    }

    public function test_teacher_with_no_assignment_sees_nothing(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->resultRun($school, $scaffold, ['status' => 'published']);

        $user = $this->actingAsMemberOf($school, Role::Teacher);
        $this->enterSchool($school);
        Teacher::factory()->create(['user_id' => $user->id]);
        $this->app->forgetScopedInstances();

        $this->enterSchool($school);
        $page = app(AcademicReport::class)->resultRunSummary([], $user);
        $this->assertSame(0, $page->total());
    }

    public function test_filters_narrow_result_run_summary_by_session_and_status(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        $period2 = $this->secondPeriod($scaffold);
        $this->resultRun($school, $scaffold, ['status' => 'draft']);
        $this->resultRun($school, $scaffold, ['status' => 'published', 'academic_period_id' => $period2->id]);

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(AcademicReport::class)->resultRunSummary(['status' => 'published'], $admin);

        $this->assertSame(1, $page->total());
    }
}
