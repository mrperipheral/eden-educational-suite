<?php

namespace Tests\Feature\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionRecordStatus;
use App\Enums\StudentStatus;
use App\Models\PromotionRecord;
use App\Models\User;
use App\Services\Promotion\PromotionService;

class PromotionServiceTest extends PromotionTestCase
{
    private function service(): PromotionService
    {
        return app(PromotionService::class);
    }

    public function test_promoting_a_single_eligible_student_creates_a_new_active_enrollment(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $by = User::factory()->create();

        $batch = $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], 'Promoted to next class.', $by,
        );

        $this->assertSame(PromotionBatchStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->records()->count());

        $record = $batch->records()->first();
        $this->assertSame(PromotionRecordStatus::Promoted, $record->status);

        $student->refresh();
        $current = $student->currentEnrollment;
        $this->assertNotNull($current);
        $this->assertSame($target['session']->id, $current->academic_session_id);
        $this->assertSame($target['level']->id, $current->academic_level_id);
        $this->assertSame($target['arm']->id, $current->level_arm_id);
        $this->assertSame(EnrollmentStatus::Active, $current->status);
    }

    public function test_promotion_preserves_the_source_enrollment_as_completed(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $sourceEnrollmentId = $student->currentEnrollment->id;
        $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, User::factory()->create(),
        );

        $sourceEnrollment = $student->enrollments()->find($sourceEnrollmentId);
        $this->assertNotNull($sourceEnrollment, 'the original enrollment row must never be deleted');
        $this->assertSame(EnrollmentStatus::Completed, $sourceEnrollment->status);
        $this->assertSame($source['session']->id, $sourceEnrollment->academic_session_id);
        $this->assertSame($source['level']->id, $sourceEnrollment->academic_level_id);
        $this->assertNotNull($sourceEnrollment->ended_on, 'a closed enrollment gets an end date');
    }

    public function test_bulk_promotion_processes_every_selected_student(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $students = collect([
            $this->enrolledStudent($school, $source),
            $this->enrolledStudent($school, $source),
            $this->enrolledStudent($school, $source),
        ]);

        $this->enterSchool($school);
        $batch = $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            $students->pluck('id')->all(), null, User::factory()->create(),
        );

        $this->assertSame(PromotionBatchStatus::Completed, $batch->status);
        $this->assertSame(3, $batch->records()->where('status', PromotionRecordStatus::Promoted->value)->count());

        foreach ($students as $student) {
            $student->refresh();
            $this->assertSame($target['level']->id, $student->currentEnrollment->academic_level_id);
        }
    }

    public function test_a_student_already_enrolled_in_the_target_session_is_skipped_not_duplicated(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $by = User::factory()->create();
        $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, $by,
        );

        // Re-source the (now-promoted) student's new placement and attempt to
        // promote again into the very same target session.
        $student->refresh();

        $batch2 = $this->service()->promoteBatch(
            $target['session'], null, $target['level'], $target['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, $by,
        );

        $this->assertSame(PromotionBatchStatus::Failed, $batch2->status, 'no student was promoted, so the batch is failed');
        $record = $batch2->records()->first();
        $this->assertSame(PromotionRecordStatus::Skipped, $record->status);

        $this->assertSame(
            1,
            $student->enrollments()->where('academic_session_id', $target['session']->id)->count(),
            'exactly one enrollment for the target session — no duplicate was created'
        );
    }

    public function test_running_the_same_batch_twice_never_double_enrolls_the_student(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $by = User::factory()->create();

        // Simulates two administrators submitting the same promotion at
        // once: the second run re-checks "already in target session" and
        // finds it true, so it skips rather than double-enrolling (M21 spec §13).
        $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, $by,
        );
        $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, $by,
        );

        $this->assertSame(
            1,
            $student->enrollments()->where('academic_session_id', $target['session']->id)->where('status', EnrollmentStatus::Active->value)->count()
        );
    }

    public function test_a_graduated_student_included_in_the_selection_is_not_promoted(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source, ['status' => StudentStatus::Graduated->value]);

        $this->enterSchool($school);
        $batch = $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], null, User::factory()->create(),
        );

        $this->assertSame(PromotionBatchStatus::Failed, $batch->status);
        $this->assertSame(PromotionRecordStatus::Failed, $batch->records()->first()->status);
        $this->assertSame(0, $student->enrollments()->where('academic_session_id', $target['session']->id)->count());
    }

    public function test_a_nonexistent_student_id_in_the_batch_fails_without_aborting_the_rest(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $realStudent = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $batch = $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$realStudent->id, 999999], null, User::factory()->create(),
        );

        $this->assertSame(PromotionBatchStatus::PartiallyCompleted, $batch->status, 'one promoted, one failed — a mix, not all-or-nothing');
        $this->assertSame(1, $batch->records()->where('status', PromotionRecordStatus::Promoted->value)->count());
        // The nonexistent id has no student row to attach a promotion_record
        // to (its FK requires a real student) — it is still correctly
        // tallied as failed in the batch's own aggregate status above,
        // it just leaves no history row of its own.
        $this->assertSame(0, $batch->records()->where('status', PromotionRecordStatus::Failed->value)->count());

        $realStudent->refresh();
        $this->assertSame($target['level']->id, $realStudent->currentEnrollment->academic_level_id);
    }

    public function test_exactly_one_promotion_record_per_student_per_batch(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);

        // A duplicate id in the submitted list (e.g. a double form submit)
        // is de-duplicated before processing, so it can only ever produce one record.
        $batch = $this->service()->promoteBatch(
            $source['session'], null, $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id, $student->id], null, User::factory()->create(),
        );

        $this->assertSame(1, PromotionRecord::query()->where('promotion_batch_id', $batch->id)->where('student_id', $student->id)->count());
    }

    public function test_batch_records_the_school_configured_source_and_target_context(): void
    {
        $school = $this->newSchool();
        $source = $this->classContext($school);
        $target = $this->classContext($school);
        $student = $this->enrolledStudent($school, $source);

        $this->enterSchool($school);
        $batch = $this->service()->promoteBatch(
            $source['session'], $source['period'], $source['level'], $source['arm'],
            $target['session'], $target['level'], $target['arm'],
            [$student->id], 'Some notes.', User::factory()->create(),
        );

        $this->assertSame($source['session']->id, $batch->source_academic_session_id);
        $this->assertSame($source['period']->id, $batch->source_academic_period_id);
        $this->assertSame($source['level']->id, $batch->source_academic_level_id);
        $this->assertSame($source['arm']->id, $batch->source_level_arm_id);
        $this->assertSame($target['session']->id, $batch->target_academic_session_id);
        $this->assertSame($target['level']->id, $batch->target_academic_level_id);
        $this->assertSame($target['arm']->id, $batch->target_level_arm_id);
        $this->assertSame('Some notes.', $batch->notes);
        $this->assertNotNull($batch->created_by);
    }
}
