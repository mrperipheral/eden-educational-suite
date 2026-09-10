<?php

namespace Tests\Feature\Assessment;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\QueryException;

class AssessmentScoreTest extends AssessmentTestCase
{
    /** @return array<int, array{score: mixed}> */
    private function scoreMap(iterable $students, mixed $score): array
    {
        $out = [];
        foreach ($students as $s) {
            $out[$s->id] = ['score' => $score];
        }

        return $out;
    }

    public function test_valid_scores_including_zero_and_the_maximum_are_saved(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 3)->values();
        $assessment = $this->assessmentFor($school, $scaffold, ['max_score' => 20]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [
            $students[0]->id => ['score' => 0],
            $students[1]->id => ['score' => 20],
            $students[2]->id => ['score' => '13.5'],
        ]])->assertRedirect(route('assessments.scores.edit', $assessment->id));

        $this->assertSame('0.00', $assessment->scores()->where('student_id', $students[0]->id)->value('score'));
        $this->assertSame('20.00', $assessment->scores()->where('student_id', $students[1]->id)->value('score'));
        $this->assertSame('13.50', $assessment->scores()->where('student_id', $students[2]->id)->value('score'));
        $this->assertNotNull($assessment->scores()->whereNotNull('recorded_at')->first());
    }

    public function test_a_negative_score_is_rejected(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => -1]]])
            ->assertSessionHasErrors('scores.'.$student->id.'.score');
    }

    public function test_a_score_above_the_maximum_is_rejected(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment(['max_score' => 10]);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 10.01]]])
            ->assertSessionHasErrors('scores.'.$student->id.'.score');

        $this->assertNull($assessment->scores()->where('student_id', $student->id)->value('score'));
    }

    public function test_a_non_numeric_score_is_rejected(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        foreach (['abc', 'NaN', 'Infinity', '1e999'] as $bad) {
            $this->from(route('assessments.scores.edit', $assessment->id))
                ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => $bad]]])
                ->assertSessionHasErrors('scores.'.$student->id.'.score');
        }
    }

    public function test_a_score_with_more_than_two_decimals_is_rejected(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => '12.345']]])
            ->assertSessionHasErrors('scores.'.$student->id.'.score');
    }

    public function test_a_blank_score_clears_it_and_a_comment_is_recorded(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 8, 'comment' => 'Good effort']]]);
        $this->assertSame('8.00', $assessment->scores()->where('student_id', $student->id)->value('score'));

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => '', 'comment' => 'Good effort']]]);
        $this->assertNull($assessment->scores()->where('student_id', $student->id)->value('score'));
        $this->assertSame('Good effort', $assessment->scores()->where('student_id', $student->id)->value('comment'));
    }

    public function test_a_comment_is_length_limited(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 5, 'comment' => str_repeat('x', 501)]]])
            ->assertSessionHasErrors('scores.'.$student->id.'.comment');
    }

    public function test_scores_can_be_corrected_before_the_assessment_is_locked(): void
    {
        [$assessment, $student, $school] = $this->oneStudentAssessment();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 5]]]);
        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 9]]]);

        $this->assertSame('9.00', $assessment->scores()->where('student_id', $student->id)->value('score'));
    }

    public function test_a_student_not_on_the_assessment_is_rejected(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enrolledStudents($school, $scaffold, 2);
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->snapshotRoster($school, $assessment);
        $this->enterSchool($school);
        $stranger = Student::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->from(route('assessments.scores.edit', $assessment->id))
            ->patch("/assessments/{$assessment->id}/scores", ['scores' => [$stranger->id => ['score' => 5]]])
            ->assertSessionHasErrors('scores');
    }

    public function test_the_database_prevents_duplicate_score_rows(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold);
        $this->enterSchool($school);
        $assessment->scores()->create(['student_id' => $student->id, 'score' => 5]);

        $this->expectException(QueryException::class);
        $assessment->scores()->create(['student_id' => $student->id, 'score' => 8]);
    }

    public function test_a_full_class_can_be_scored_in_one_request(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 12);
        $assessment = $this->assessmentFor($school, $scaffold, ['max_score' => 50]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => $this->scoreMap($students, 40)])
            ->assertSessionHasNoErrors();

        $this->assertSame(12, $assessment->scores()->where('score', 40)->count());
    }

    public function test_scores_cannot_be_changed_once_locked(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold, ['status' => 'locked', 'published_at' => now(), 'locked_at' => now()]);
        $this->snapshotRoster($school, $assessment);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        $this->patch("/assessments/{$assessment->id}/scores", ['scores' => [$student->id => ['score' => 5]]])->assertForbidden();
        $this->get("/assessments/{$assessment->id}/scores")->assertForbidden();

        $this->assertNull($assessment->scores()->where('student_id', $student->id)->value('score'));
    }

    /**
     * @return array{0: Assessment, 1: Student, 2: School}
     */
    private function oneStudentAssessment(array $attributes = []): array
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudents($school, $scaffold, 1)->first();
        $assessment = $this->assessmentFor($school, $scaffold, $attributes);
        $this->snapshotRoster($school, $assessment);

        return [$assessment, $student, $school];
    }
}
