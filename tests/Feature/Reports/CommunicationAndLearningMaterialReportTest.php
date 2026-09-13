<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\CommunicationThread;
use App\Models\LearningMaterial;
use App\Reports\CommunicationReport;
use App\Reports\LearningMaterialReport;

/**
 * `CommunicationReport` (M27 §12) and `LearningMaterialReport` (M27 §11) —
 * lightweight administrative summaries, not analytics/tracking systems.
 */
class CommunicationAndLearningMaterialReportTest extends ReportsTestCase
{
    public function test_communication_summary_counts_threads_by_status_and_category(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        CommunicationThread::factory()->create(['status' => 'open', 'category' => 'academic']);
        CommunicationThread::factory()->create(['status' => 'resolved', 'category' => 'academic']);
        Announcement::factory()->create(['status' => 'published']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(CommunicationReport::class)->summary();

        $this->assertSame(2, $summary['threads_total']);
        $this->assertSame(1, (int) ($summary['threads_by_status']['open'] ?? 0));
        $this->assertSame(1, $summary['announcements_published']);
    }

    public function test_learning_material_summary_counts_by_subject_and_type(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);
        $this->enterSchool($school);
        LearningMaterial::factory()->create(['subject_id' => $scaffold['subject']->id, 'type' => 'document']);
        LearningMaterial::factory()->create(['subject_id' => $scaffold['subject']->id, 'type' => 'video']);
        $this->app->forgetScopedInstances();

        $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $summary = app(LearningMaterialReport::class)->summary();

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, (int) ($summary['by_type']['document'] ?? 0));
        $this->assertSame(1, (int) ($summary['by_type']['video'] ?? 0));
    }
}
