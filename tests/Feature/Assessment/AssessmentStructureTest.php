<?php

namespace Tests\Feature\Assessment;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domain boundaries and performance guarantees for M14:
 *   School → Assessments / Categories / Assignments ·
 *   Assessment → Scores · Score → Assessment / Student
 * with no premature derived fields, and historical scores surviving a student
 * leaving.
 */
class AssessmentStructureTest extends AssessmentTestCase
{
    public function test_school_owns_assessments_and_assessments_own_scores(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->enterSchool($school);

        $this->assertSame(1, $school->assessments()->count());
        $this->assertSame(3, $assessment->scores()->count());

        $score = AssessmentScore::query()->with(['assessment', 'student'])->first();
        $this->assertTrue($score->assessment->is($assessment));
        $this->assertTrue($students->contains($score->student));
    }

    public function test_the_assessments_table_carries_no_premature_derived_fields(): void
    {
        $columns = Schema::getColumnListing('assessments');
        sort($columns);

        $this->assertSame([
            'academic_level_id', 'academic_period_id', 'academic_session_id', 'assessment_category_id',
            'assessment_date', 'assignment_id', 'created_at', 'created_by', 'id', 'instructions',
            'level_arm_id', 'locked_at', 'locked_by', 'max_score', 'published_at', 'school_id',
            'status', 'subject_id', 'title', 'updated_at',
        ], $columns);

        foreach (['final_grade', 'percentage', 'percentage_grade', 'subject_average', 'position', 'rank', 'gpa', 'grade'] as $forbidden) {
            $this->assertFalse(Schema::hasColumn('assessments', $forbidden), "assessments.{$forbidden} should not exist in M14");
        }
    }

    public function test_the_scores_table_stores_only_source_data(): void
    {
        $columns = Schema::getColumnListing('assessment_scores');
        sort($columns);

        $this->assertSame([
            'assessment_id', 'comment', 'created_at', 'id', 'recorded_at', 'recorded_by',
            'school_id', 'score', 'student_id', 'updated_at',
        ], $columns);

        foreach (['grade', 'percentage', 'remark_grade', 'position'] as $forbidden) {
            $this->assertFalse(Schema::hasColumn('assessment_scores', $forbidden));
        }
    }

    public function test_the_assessment_model_exposes_no_result_module_relationships(): void
    {
        $assessment = new Assessment;

        foreach (['result', 'results', 'reportCard', 'grade', 'grades', 'gradingScheme'] as $relation) {
            $this->assertFalse(method_exists($assessment, $relation), "Assessment should not define `{$relation}()`");
        }
    }

    public function test_a_score_stays_when_the_student_later_withdraws_or_changes_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 14]]]);

        $this->enterSchool($school);
        $student->enrollments()->update([
            'status' => EnrollmentStatus::Withdrawn->value,
            'ended_on' => now()->addDay()->toDateString(),
        ]);
        Student::withoutGlobalScopes()->whereKey($student->id)->update(['status' => StudentStatus::Withdrawn->value]);

        $score = $assessment->scores()->where('student_id', $student->id)->first();
        $this->assertNotNull($score);
        $this->assertSame('14.00', $score->score);
    }

    public function test_deleting_a_student_cascades_scores_but_leaves_the_assessment(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->enterSchool($school);

        Student::withoutGlobalScopes()->whereKey($students->first()->id)->delete();

        $this->assertNotNull($assessment->fresh());
        $this->assertSame(1, $assessment->scores()->count());
    }

    public function test_a_locked_assessment_stays_readable(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'locked', 'published_at' => now(), 'locked_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::Staff);

        $this->get("/assessments/{$assessment->id}")->assertOk();
    }

    public function test_the_score_sheet_does_not_n_plus_one_for_a_full_class(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 40);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get("/assessments/{$assessment->id}/scores")->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "the score sheet ran {$queries} queries for 40 students");
    }

    public function test_the_assessment_list_does_not_n_plus_one(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold);
        foreach (range(1, 15) as $i) {
            $this->assessmentFor($school, $scaffold, ['assessment_date' => now()->subDays($i)->toDateString()]);
        }
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/assessments')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $queries, "the assessment list ran {$queries} queries for 15 assessments");
    }

    public function test_bulk_score_save_does_not_run_a_query_per_student_for_reads(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 40);
        $assessment = $this->assessmentFor($school, $scaffold, ['max_score' => 50]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $scores = [];
        foreach ($students as $s) {
            $scores[$s->id] = ['score' => 30];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => $scores])->assertSessionHasNoErrors();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(40 + 20, $queries, "bulk score save ran {$queries} queries for 40 students");
    }
}
