<?php

namespace Tests\Feature\Cbt;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\Student;
use App\Models\User;
use App\Services\Cbt\ExamAttemptService;
use App\Services\Cbt\ExaminationQuestionService;
use Illuminate\Support\Carbon;

class ResultReleaseTest extends CbtTestCase
{
    private function attemptedExam(array $overrides = []): array
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, array_merge(['status' => 'draft'], $overrides));
        $question = $this->questionIn($school, $context['subject']);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $exam->schedule();
        $exam = $exam->fresh();

        $service = app(ExamAttemptService::class);
        $attempt = $service->start($exam, $student);
        $q = $exam->questions()->with('options')->first();
        $service->answer($exam, $attempt, $q, $q->options->firstWhere('is_correct', true)->id);
        $attempt = $service->submit($exam, $attempt);

        return [$exam, $attempt];
    }

    public function test_immediate_release_is_visible_right_after_submission(): void
    {
        [$exam, $attempt] = $this->attemptedExam(['result_release' => 'immediate', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2)]);

        $this->assertTrue($attempt->isResultVisible());
    }

    public function test_scheduled_release_is_hidden_before_the_release_time(): void
    {
        [$exam, $attempt] = $this->attemptedExam([
            'result_release' => 'scheduled',
            'result_release_at' => now()->addDay(),
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertFalse($attempt->isResultVisible());
    }

    public function test_scheduled_release_becomes_visible_once_the_server_clock_passes_it(): void
    {
        [$exam, $attempt] = $this->attemptedExam([
            'result_release' => 'scheduled',
            'result_release_at' => now()->addMinutes(5),
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertFalse($attempt->isResultVisible(Carbon::now()));
        $this->assertTrue($attempt->isResultVisible(Carbon::now()->addMinutes(10)), 'visible once the server clock reaches the release time — no manual staff action required');
    }

    public function test_result_is_never_visible_while_the_attempt_is_still_in_progress(): void
    {
        $school = $this->newSchool();
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'draft', 'result_release' => 'immediate', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2)]);
        $question = $this->questionIn($school, $context['subject']);
        $student = $this->enrolledStudent($school, $context);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $exam->schedule();
        $exam = $exam->fresh();

        $attempt = app(ExamAttemptService::class)->start($exam, $student);

        $this->assertFalse($attempt->isResultVisible());
    }

    public function test_the_student_portal_hides_score_before_release_but_shows_completion(): void
    {
        [$exam, $attempt] = $this->attemptedExam([
            'result_release' => 'scheduled',
            'result_release_at' => now()->addDay(),
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);

        $student = Student::query()->whereKey($attempt->student_id)->first();
        $user = User::factory()->create();
        $this->enterSchool($exam->school);
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $user->joinSchool($exam->school, Role::Student);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $exam->school_id]);

        $response = $this->get("/student/cbt/{$exam->id}/result");

        $response->assertOk();
        $response->assertSee('submitted successfully', false);
        $response->assertDontSee((string) $attempt->percentage);
        $response->assertDontSee((string) $attempt->score);
    }

    public function test_the_student_portal_shows_the_score_after_release(): void
    {
        [$exam, $attempt] = $this->attemptedExam([
            'result_release' => 'immediate',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2),
        ]);

        $student = Student::query()->whereKey($attempt->student_id)->first();
        $user = User::factory()->create();
        $this->enterSchool($exam->school);
        $student->user_id = $user->id;
        $student->save();
        $this->app->forgetScopedInstances();

        $user->joinSchool($exam->school, Role::Student);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $exam->school_id]);

        $response = $this->get("/student/cbt/{$exam->id}/result");

        $response->assertOk();
        $response->assertSee($attempt->passed ? 'Pass' : 'Fail');
    }

    public function test_correct_answers_are_never_present_in_the_take_page_payload(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'draft', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(2)]);
        $question = $this->questionIn($school, $context['subject']);
        [$user, $student] = $this->studentUserFor($school, $context);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $exam->refresh();
        $exam->schedule();
        $this->app->forgetScopedInstances();

        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->id]);

        $this->post("/student/cbt/{$exam->id}/start");
        $response = $this->get("/student/cbt/{$exam->id}/take");

        $response->assertOk();
        $response->assertDontSee('is_correct', false);
        $response->assertDontSee('"correct":true', false);
    }
}
