<?php

namespace App\Services\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionRecordStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicPeriod;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\LevelArm;
use App\Models\PromotionBatch;
use App\Models\PromotionRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs a bulk promotion: creates a {@see PromotionBatch}, then processes
 * each selected student **independently** — one student's failure never
 * rolls back another's success (M21 spec §4). Reuses M9's own
 * `Enrollment`/`Enrollment::makeActive()` exactly as an ordinary class
 * change already works: the source enrollment is never edited or deleted,
 * only closed (`completed`) by `makeActive()` the moment the new one
 * becomes the student's active placement. See `docs/promotion.md`.
 *
 * Every student's transition is wrapped in its own `DB::transaction()` with
 * the `Student` row `lockForUpdate()`-ed for its duration — the concurrency
 * guard against two administrators promoting the same student at once (M21
 * spec §13): whichever request's transaction commits first wins; the other
 * re-checks "already in target session" after acquiring the lock and finds
 * it true, so it skips rather than double-enrolling.
 */
class PromotionService
{
    public function __construct(private readonly PromotionEligibilityService $eligibility) {}

    /**
     * @param  list<int>  $studentIds
     */
    public function promoteBatch(
        AcademicSession $sourceSession,
        ?AcademicPeriod $sourcePeriod,
        AcademicLevel $sourceLevel,
        ?LevelArm $sourceArm,
        AcademicSession $targetSession,
        AcademicLevel $targetLevel,
        ?LevelArm $targetArm,
        array $studentIds,
        ?string $notes,
        User $by,
    ): PromotionBatch {
        $batch = new PromotionBatch([
            'source_academic_session_id' => $sourceSession->getKey(),
            'source_academic_period_id' => $sourcePeriod?->getKey(),
            'source_academic_level_id' => $sourceLevel->getKey(),
            'source_level_arm_id' => $sourceArm?->getKey(),
            'target_academic_session_id' => $targetSession->getKey(),
            'target_academic_level_id' => $targetLevel->getKey(),
            'target_level_arm_id' => $targetArm?->getKey(),
            'notes' => $notes,
        ]);
        $batch->created_by = $by->getKey();
        $batch->status = PromotionBatchStatus::Failed->value;
        $batch->save();

        $eligibleIds = $this->eligibility->eligibleQuery($sourceSession, $sourceLevel, $sourceArm)->pluck('id');

        $promoted = 0;
        $skipped = 0;
        $failed = 0;

        foreach (array_unique($studentIds) as $studentId) {
            $status = $this->promoteOne($batch, (int) $studentId, $eligibleIds, $targetSession, $targetLevel, $targetArm);

            match ($status) {
                PromotionRecordStatus::Promoted => $promoted++,
                PromotionRecordStatus::Skipped => $skipped++,
                PromotionRecordStatus::Failed => $failed++,
            };
        }

        $batch->status = $this->aggregateStatus($promoted, count(array_unique($studentIds)))->value;
        $batch->save();

        return $batch;
    }

    private function promoteOne(
        PromotionBatch $batch,
        int $studentId,
        Collection $eligibleIds,
        AcademicSession $targetSession,
        AcademicLevel $targetLevel,
        ?LevelArm $targetArm,
    ): PromotionRecordStatus {
        try {
            return DB::transaction(function () use ($batch, $studentId, $eligibleIds, $targetSession, $targetLevel, $targetArm) {
                $student = Student::query()->whereKey($studentId)->lockForUpdate()->first();

                if ($student === null || ! $eligibleIds->contains($studentId)) {
                    $this->recordOutcome($batch, $studentId, null, null, PromotionRecordStatus::Failed, __('Not eligible for this source placement.'));

                    return PromotionRecordStatus::Failed;
                }

                $sourceEnrollment = $student->currentEnrollment;

                if ($sourceEnrollment === null) {
                    $this->recordOutcome($batch, $studentId, null, null, PromotionRecordStatus::Failed, __('No current enrollment found.'));

                    return PromotionRecordStatus::Failed;
                }

                if ($this->eligibility->alreadyInTargetSession($student, $targetSession)) {
                    $this->recordOutcome($batch, $studentId, $sourceEnrollment->getKey(), null, PromotionRecordStatus::Skipped, __('Already enrolled in the target session.'));

                    return PromotionRecordStatus::Skipped;
                }

                $targetEnrollment = new Enrollment([
                    'academic_session_id' => $targetSession->getKey(),
                    'academic_level_id' => $targetLevel->getKey(),
                    'level_arm_id' => $targetArm?->getKey(),
                    'status' => EnrollmentStatus::Active->value,
                    'started_on' => $targetSession->starts_on,
                ]);
                $targetEnrollment->student_id = $studentId;
                $targetEnrollment->save();
                $targetEnrollment->makeActive();

                $this->recordOutcome($batch, $studentId, $sourceEnrollment->getKey(), $targetEnrollment->getKey(), PromotionRecordStatus::Promoted, null);

                return PromotionRecordStatus::Promoted;
            });
        } catch (\Throwable $e) {
            // The transaction above has already rolled back — nothing
            // partial was left behind. Log the failure outside it. This can
            // itself fail to persist (e.g. `$studentId` matches no student
            // row anywhere — never possible via the validated HTTP path,
            // only a direct service call bypassing Form Request validation
            // — `promotion_records.student_id` has nothing to reference);
            // the batch's promoted/skipped/failed tally below is driven by
            // the returned status regardless, so a missing row never hides
            // the failure from the caller.
            try {
                $this->recordOutcome($batch, $studentId, null, null, PromotionRecordStatus::Failed, __('Unexpected error: :message', ['message' => $e->getMessage()]));
            } catch (\Throwable) {
                // Nothing to attach the record to — already counted as failed below.
            }

            return PromotionRecordStatus::Failed;
        }
    }

    private function recordOutcome(
        PromotionBatch $batch,
        int $studentId,
        ?int $sourceEnrollmentId,
        ?int $targetEnrollmentId,
        PromotionRecordStatus $status,
        ?string $reason,
    ): void {
        $record = new PromotionRecord;
        $record->promotion_batch_id = $batch->getKey();
        $record->student_id = $studentId;
        $record->source_enrollment_id = $sourceEnrollmentId;
        $record->target_enrollment_id = $targetEnrollmentId;
        $record->status = $status->value;
        $record->failure_reason = $reason;
        $record->save();
    }

    /** No student promoted → `failed`; every one promoted → `completed`; otherwise `partially_completed`. */
    private function aggregateStatus(int $promoted, int $total): PromotionBatchStatus
    {
        if ($promoted === 0) {
            return PromotionBatchStatus::Failed;
        }

        if ($promoted === $total) {
            return PromotionBatchStatus::Completed;
        }

        return PromotionBatchStatus::PartiallyCompleted;
    }
}
