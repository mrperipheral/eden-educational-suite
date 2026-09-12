<?php

namespace Tests\Feature\Cbt;

use App\Enums\ExaminationStatus;
use App\Enums\Role;
use App\Models\Examination;
use App\Models\Subject;
use App\Services\Cbt\ExaminationQuestionService;

class ExaminationTest extends CbtTestCase
{
    private function payload(array $context, array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $context['session']->id,
            'academic_period_id' => $context['period']->id,
            'academic_level_id' => $context['level']->id,
            'level_arm_id' => $context['arm']->id,
            'subject_id' => $context['subject']->id,
            'title' => 'Mid-Term Test',
            'description' => 'Covers chapters 1-3.',
            'duration_minutes' => 30,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(3)->format('Y-m-d\TH:i'),
            'pass_mark_percentage' => 50,
            'result_release' => 'immediate',
        ], $overrides);
    }

    public function test_admin_can_create_an_examination_as_draft(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/examinations', $this->payload($context));

        $exam = Examination::query()->firstOrFail();
        $response->assertRedirect(route('cbt.examinations.show', $exam->id));
        $this->assertSame(ExaminationStatus::Draft, $exam->status);
        $this->assertNotNull($exam->created_by);
    }

    public function test_scheduled_release_requires_a_release_time(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/examinations', $this->payload($context, [
            'result_release' => 'scheduled',
            'result_release_at' => null,
        ]));

        $response->assertSessionHasErrors('result_release_at');
    }

    public function test_ends_at_must_be_after_starts_at(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/examinations', $this->payload($context, [
            'ends_at' => now()->addDay()->subHour()->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_subject_not_offered_at_level_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->enterSchool($school);
        $otherSubject = Subject::factory()->create();
        $this->app->forgetScopedInstances();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->post('/cbt/examinations', $this->payload($context, ['subject_id' => $otherSubject->id]));

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_draft_examination_can_be_edited(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['title' => 'Original']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/edit")->assertOk();

        $this->patch("/cbt/examinations/{$exam->id}", $this->payload($context, ['title' => 'Renamed']))
            ->assertRedirect(route('cbt.examinations.show', $exam->id));

        $this->assertSame('Renamed', $exam->fresh()->title);
    }

    public function test_scheduling_requires_at_least_one_question(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/schedule")->assertRedirect();

        $this->assertSame(ExaminationStatus::Draft, $exam->fresh()->status);
    }

    public function test_scheduling_a_draft_with_a_question_succeeds(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $question = $this->questionIn($school, $context['subject']);

        $this->enterSchool($school);
        app(ExaminationQuestionService::class)->attach($exam, $question);
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::SchoolAdmin);
        $this->post("/cbt/examinations/{$exam->id}/schedule")->assertRedirect(route('cbt.examinations.show', $exam->id));

        $this->assertSame(ExaminationStatus::Scheduled, $exam->fresh()->status);
        $this->assertNotNull($exam->fresh()->scheduled_at);
    }

    public function test_a_scheduled_examinations_metadata_cannot_be_edited(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'scheduled', 'scheduled_at' => now()]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$exam->id}/edit")->assertForbidden();
        $this->patch("/cbt/examinations/{$exam->id}", $this->payload($context, ['title' => 'Nope']))->assertForbidden();
    }

    public function test_a_draft_examination_cannot_be_closed(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/close")->assertRedirect();

        $this->assertSame(ExaminationStatus::Draft, $exam->fresh()->status);
    }

    public function test_a_scheduled_examination_can_be_closed(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'scheduled', 'scheduled_at' => now()]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/close")->assertRedirect(route('cbt.examinations.show', $exam->id));

        $this->assertSame(ExaminationStatus::Closed, $exam->fresh()->status);
        $this->assertNotNull($exam->fresh()->closed_at);
    }

    public function test_a_closed_examination_cannot_be_scheduled_again(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $exam = $this->examinationIn($school, $context, ['status' => 'closed', 'scheduled_at' => now()->subHour(), 'closed_at' => now()]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post("/cbt/examinations/{$exam->id}/schedule")->assertRedirect();

        $this->assertSame(ExaminationStatus::Closed, $exam->fresh()->status);
    }

    public function test_a_school_cannot_view_another_schools_examination(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $examB = $this->examinationIn($schoolB, $contextB);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get("/cbt/examinations/{$examB->id}")->assertNotFound();
    }

    public function test_a_school_cannot_create_using_another_schools_academic_ids(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->enableCbt($schoolA);
        $contextB = $this->classContext($schoolB);
        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $response = $this->post('/cbt/examinations', $this->payload($contextB));

        $response->assertSessionHasErrors(['academic_session_id', 'academic_level_id', 'level_arm_id', 'subject_id']);
    }

    public function test_school_id_from_the_browser_is_ignored(): void
    {
        $school = $this->newSchool();
        $this->enableCbt($school);
        $context = $this->classContext($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->post('/cbt/examinations', $this->payload($context, ['school_id' => 999999]));

        $exam = Examination::query()->firstOrFail();
        $this->assertSame($school->id, $exam->school_id);
    }

    public function test_module_off_returns_404(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/cbt/examinations')->assertNotFound();
    }
}
