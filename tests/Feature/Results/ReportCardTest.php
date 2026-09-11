<?php

namespace Tests\Feature\Results;

use App\Enums\Role;
use App\Models\ReportCardConfiguration;
use App\Models\StudentResult;

class ReportCardTest extends ResultsTestCase
{
    private function publishedResult(): array
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $this->post("/results/runs/{$run->id}/review");
        $this->post("/results/runs/{$run->id}/approve");
        $this->post("/results/runs/{$run->id}/publish");
        $this->flushSession();

        $studentResult = StudentResult::query()->where('result_run_id', $run->id)->firstOrFail();

        return [$school, $run, $studentResult];
    }

    public function test_a_report_card_shows_only_enabled_fields(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();
        $this->enterSchool($school);
        ReportCardConfiguration::query()->create([
            'academic_session_id' => null, 'academic_period_id' => null,
            'show_overall_position' => false, 'show_subject_grade' => true,
        ]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $response = $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")->assertOk();

        $response->assertSee(__('Grade'));
        $response->assertDontSee(__('Class position'));
    }

    public function test_configuration_scope_precedence_session_beats_school_wide(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();
        $this->enterSchool($school);
        ReportCardConfiguration::query()->create(['academic_session_id' => null, 'academic_period_id' => null, 'show_overall_average' => false]);
        ReportCardConfiguration::query()->create(['academic_session_id' => $run->academic_session_id, 'academic_period_id' => null, 'show_overall_average' => true]);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")
            ->assertOk()->assertSee(__('Average'));
    }

    public function test_the_report_card_shows_branding_comments_and_position(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();
        $this->enterSchool($school);
        $studentResult->update(['class_teacher_comment' => 'Keep up the good work.']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $response = $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")->assertOk();

        $response->assertSee($school->name);
        $response->assertSee('Keep up the good work.');
        $response->assertSee((string) $studentResult->fresh()->position);
    }

    public function test_report_cards_are_not_available_before_compilation(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);

        // No student result exists yet on a draft run.
        $this->get("/results/runs/{$run->id}/students/1/report-card")->assertNotFound();
    }

    public function test_report_cards_are_tenant_isolated(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();
        $b = $this->newSchool();

        $this->actingAsMemberOf($b, Role::SchoolAdmin);
        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")->assertNotFound();
    }

    public function test_publishing_snapshots_the_configuration_for_the_run(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();

        $snapshot = ReportCardConfiguration::forRun($run->fresh());
        $this->assertNotNull($snapshot);
        $this->assertNotNull($snapshot->result_run_id);
    }

    public function test_changing_the_live_configuration_does_not_alter_a_locked_reports_fields(): void
    {
        [$school, $run, $studentResult] = $this->publishedResult();
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/lock");
        $this->flushSession();

        // Change the live, school-wide configuration after locking — via the
        // same exact-scope lookup the real controller uses, which excludes
        // the run's own frozen snapshot row (also scoped session=null/period=null).
        $this->enterSchool($school);
        $live = ReportCardConfiguration::exactScopeRow(null, null);
        $live->fill(['show_overall_average' => false]);
        $live->save();
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::Staff);
        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")
            ->assertOk()->assertSee(__('Average'), false);
    }

    public function test_an_unpublished_runs_preview_reflects_the_live_configuration(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $students = $this->enrolledStudents($school, $scaffold, 1);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['classwork'], $students, [10]);
        $this->lockedAssessment($school, $scaffold, $scaffold['subject'], $scaffold['exam'], $students, [15]);
        $run = $this->resultRun($school, $scaffold);
        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->post("/results/runs/{$run->id}/compile");
        $studentResult = StudentResult::query()->where('result_run_id', $run->id)->firstOrFail();

        $this->enterSchool($school);
        ReportCardConfiguration::query()->create(['academic_session_id' => null, 'academic_period_id' => null, 'show_overall_total' => false]);
        $this->app->forgetScopedInstances();

        $this->get("/results/runs/{$run->id}/students/{$studentResult->id}/report-card")
            ->assertOk()->assertDontSee(__('Total'));
    }
}
