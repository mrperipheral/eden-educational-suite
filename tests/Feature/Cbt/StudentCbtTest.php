<?php

namespace Tests\Feature\Cbt;

use App\Enums\ExamAttemptStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\School;
use App\Models\User;
use App\Services\Cbt\ExaminationQuestionService;

class StudentCbtTest extends CbtTestCase
{
    private function openExam(School $school, array $context, array $overrides = [])
    {
        $exam = $this->examinationIn($school, $context, array_merge([
            'status' => 'draft',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHours(2),
        ], $overrides));
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $exam->schedule();
        $exam = $exam->fresh();
        $this->app->forgetScopedInstances();

        return $exam;
    }

    public function test_student_sees_examinations_for_their_own_current_class(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context, ['title' => 'My Class Exam']);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->get('/student/cbt')->assertOk()->assertSee('My Class Exam');
    }

    public function test_student_does_not_see_a_different_arms_examination(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context, ['title' => 'Other Arm Exam']);

        $this->enterSchool($school);
        $otherArm = $context['level']->arms()->create(['name' => 'Silver', 'code' => 'S', 'position' => 2]);
        $this->app->forgetScopedInstances();
        $otherContext = $context;
        $otherContext['arm'] = $otherArm;
        [$user, $student] = $this->studentUserFor($school, $otherContext);

        $this->get('/student/cbt')->assertOk()->assertDontSee('Other Arm Exam');
    }

    public function test_draft_examinations_are_never_shown_to_students(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->examinationIn($school, $context, ['title' => 'Still A Draft']);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->get('/student/cbt')->assertOk()->assertDontSee('Still A Draft');
    }

    public function test_student_can_start_take_and_submit_an_exam_end_to_end(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->post("/student/cbt/{$exam->id}/start")->assertRedirect(route('student.cbt.take', $exam->id));

        $attempt = ExamAttempt::query()->where('examination_id', $exam->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(ExamAttemptStatus::InProgress, $attempt->status);

        $this->get("/student/cbt/{$exam->id}/take")->assertOk();

        $this->post("/student/cbt/{$exam->id}/submit")->assertRedirect(route('student.cbt.result', $exam->id));

        $attempt->refresh();
        $this->assertSame(ExamAttemptStatus::Completed, $attempt->status);
    }

    public function test_duplicate_submission_is_rejected_at_the_start_step(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->post("/student/cbt/{$exam->id}/start");
        $this->post("/student/cbt/{$exam->id}/submit");

        $this->post("/student/cbt/{$exam->id}/start")->assertRedirect(route('student.cbt.show', $exam->id));

        $this->assertSame(1, ExamAttempt::query()->where('examination_id', $exam->id)->where('student_id', $student->id)->count());
    }

    public function test_answer_endpoint_rejects_an_option_belonging_to_a_different_question(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'draft', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2)]);
        $q1 = $this->questionIn($school, $context['subject']);
        $q2 = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        $service = app(ExaminationQuestionService::class);
        $service->attach($exam, $q1);
        $service->attach($exam, $q2);
        $exam->refresh();
        $exam->schedule();
        $exam = $exam->fresh();
        $this->app->forgetScopedInstances();

        [$user, $student] = $this->studentUserFor($school, $context);
        $this->post("/student/cbt/{$exam->id}/start");

        $examinationQuestion1 = $exam->questions()->orderBy('position')->first();
        $examinationQuestion2 = $exam->questions()->orderBy('position')->skip(1)->first();
        $foreignOption = $examinationQuestion2->options()->first();

        $response = $this->postJson("/student/cbt/{$exam->id}/answer", [
            'examination_question_id' => $examinationQuestion1->id,
            'selected_option_id' => $foreignOption->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_a_student_cannot_access_another_students_attempt(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context);

        [$userA, $studentA] = $this->studentUserFor($school, $context);
        $this->post("/student/cbt/{$exam->id}/start");
        $attemptA = ExamAttempt::query()->where('student_id', $studentA->id)->firstOrFail();

        [$userB, $studentB] = $this->studentUserFor($school, $context);
        // Student B starts their own attempt; then we assert their "take"
        // page only ever operates on their own attempt, never A's.
        $this->post("/student/cbt/{$exam->id}/start");
        $attemptB = ExamAttempt::query()->where('student_id', $studentB->id)->firstOrFail();

        $this->assertNotSame($attemptA->id, $attemptB->id);

        // B answers a question; A's attempt must be untouched.
        $question = $exam->questions()->with('options')->first();
        $this->postJson("/student/cbt/{$exam->id}/answer", [
            'examination_question_id' => $question->id,
            'selected_option_id' => $question->options->first()->id,
        ])->assertOk();

        $answerA = ExamAnswer::query()->where('exam_attempt_id', $attemptA->id)->where('examination_question_id', $question->id)->first();
        $this->assertNull($answerA->selected_option_id, "student B's answer must never touch student A's attempt");
    }

    public function test_a_student_cannot_access_another_schools_examination(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $this->enableCbt($schoolB);
        $contextA = $this->classContext($schoolA);
        $contextB = $this->classContext($schoolB);
        $examB = $this->openExam($schoolB, $contextB);
        [$userA, $studentA] = $this->studentUserFor($schoolA, $contextA);

        $this->get("/student/cbt/{$examB->id}")->assertNotFound();
        $this->post("/student/cbt/{$examB->id}/start")->assertNotFound();
    }

    public function test_a_student_with_no_linked_record_sees_a_safe_empty_state(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $user = User::factory()->create();
        $user->joinSchool($school, Role::Student);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->id]);

        $this->get('/student/cbt')->assertOk();
    }

    public function test_non_student_role_is_forbidden_from_the_student_cbt_routes(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $this->actingAsRole($school, Role::Teacher);

        $this->get('/student/cbt')->assertForbidden();
    }

    public function test_module_off_shows_empty_state_not_404_for_the_index(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->get('/student/cbt')->assertOk();
    }

    public function test_ineligible_student_cannot_start_an_exam_via_a_manipulated_id(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->openExam($school, $context);

        // A second student in a *different* class at the same school.
        $otherContext = $this->classContext($school);
        [$user, $student] = $this->studentUserFor($school, $otherContext);

        $this->get("/student/cbt/{$exam->id}")->assertNotFound();
        $this->post("/student/cbt/{$exam->id}/start")->assertNotFound();
    }
}
