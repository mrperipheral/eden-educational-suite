<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Reports\CbtReport;

/**
 * `CbtReport` (M27 §9) — staff-only surface; reads persisted attempt
 * score/percentage/passed columns directly, never recomputes them, never
 * touches question option correctness.
 */
class CbtReportTest extends ReportsTestCase
{
    public function test_examination_summary_counts_attempts_and_averages(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);

        $this->enterSchool($school);
        $exam = Examination::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
            'status' => 'closed',
        ]);
        ExamAttempt::factory()->completed()->create([
            'examination_id' => $exam->id, 'student_id' => $students[0]->id,
            'score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true,
        ]);
        ExamAttempt::factory()->completed()->create([
            'examination_id' => $exam->id, 'student_id' => $students[1]->id,
            'score' => 4, 'max_score' => 10, 'percentage' => 40, 'passed' => false,
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(CbtReport::class)->examinationSummary([], $admin);

        $row = $page->items()[0];
        $this->assertSame(2, $row->attempts_count);
        $this->assertSame(2, $row->completed_attempts_count);
        $this->assertSame(1, $row->passed_attempts_count);
        $this->assertSame(60.0, (float) $row->average_percentage);
    }

    public function test_teacher_without_manage_only_sees_own_level_and_subject(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        $ownExam = Examination::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
        ]);
        Examination::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject2']->id,
        ]);
        $this->app->forgetScopedInstances();

        $teacher = $this->actingAsTeacherFor($school, $scaffold);
        $this->enterSchool($school);
        $page = app(CbtReport::class)->examinationSummary([], $teacher);

        $this->assertSame(1, $page->total());
        $this->assertSame($ownExam->id, $page->items()[0]->id);
    }

    public function test_attempts_lists_students_for_one_examination(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);

        $this->enterSchool($school);
        $exam = Examination::factory()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_period_id' => $scaffold['period']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'subject_id' => $scaffold['subject']->id,
        ]);
        ExamAttempt::factory()->completed()->create([
            'examination_id' => $exam->id, 'student_id' => $students[0]->id, 'percentage' => 70,
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $exam->refresh();
        $page = app(CbtReport::class)->attempts($exam);

        $this->assertSame(1, $page->total());
    }
}
