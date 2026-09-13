<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\PromotionBatch;
use App\Models\PromotionRecord;
use App\Models\Student;
use App\Reports\PromotionReport;

/**
 * `PromotionReport` (M27 §10) — reports what already happened via M21's
 * `PromotionBatch`/`PromotionRecord`/`students.status`, no placement logic.
 */
class PromotionReportTest extends ReportsTestCase
{
    public function test_batches_lists_batches_for_this_school_with_record_count(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        $batch = PromotionBatch::factory()->create([
            'source_academic_session_id' => $scaffold['session']->id,
            'source_academic_level_id' => $scaffold['level']->id,
        ]);
        $student = Student::factory()->create();
        $enrollment = $student->enrollments()->create([
            'academic_session_id' => $scaffold['session']->id,
            'academic_level_id' => $scaffold['level']->id,
            'level_arm_id' => $scaffold['arm']->id,
            'status' => 'active',
            'started_on' => $scaffold['session']->starts_on->toDateString(),
        ]);
        PromotionRecord::factory()->create([
            'promotion_batch_id' => $batch->id, 'student_id' => $student->id, 'source_enrollment_id' => $enrollment->id,
        ]);
        $this->app->forgetScopedInstances();

        $other = $this->newSchool();
        $otherScaffold = $this->scaffold($other);
        $this->enterSchool($other);
        PromotionBatch::factory()->create([
            'source_academic_session_id' => $otherScaffold['session']->id,
            'source_academic_level_id' => $otherScaffold['level']->id,
        ]);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(PromotionReport::class)->batches([]);

        $this->assertSame(1, $page->total());
        $this->assertSame(1, $page->items()[0]->records_count);
    }

    public function test_graduation_history_only_lists_graduated_students(): void
    {
        $school = $this->newSchool();
        $scaffold = $this->scaffold($school);

        $this->enterSchool($school);
        Student::factory()->create([
            'status' => 'graduated',
            'graduated_academic_session_id' => $scaffold['session']->id,
            'graduated_at' => now(),
        ]);
        Student::factory()->create(['status' => 'active']);
        $this->app->forgetScopedInstances();

        $admin = $this->actingAsMemberOf($school, Role::SchoolAdmin);
        $this->enterSchool($school);
        $page = app(PromotionReport::class)->graduationHistory([]);

        $this->assertSame(1, $page->total());
    }

    public function test_graduated_count_is_scoped_to_this_school(): void
    {
        $school = $this->newSchool();
        $this->enterSchool($school);
        Student::factory()->create(['status' => 'graduated', 'graduated_at' => now()]);
        $this->app->forgetScopedInstances();

        $other = $this->newSchool();
        $this->enterSchool($other);
        Student::factory()->count(3)->create(['status' => 'graduated', 'graduated_at' => now()]);
        $this->app->forgetScopedInstances();

        $this->enterSchool($school);
        $this->assertSame(1, app(PromotionReport::class)->graduatedCount());
    }
}
