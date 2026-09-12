<?php

namespace App\Services\Promotion;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicSession;
use App\Models\LevelArm;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves "who may be promoted from this source class?" — the single seam
 * both the promotion UI (to build the selectable roster) and
 * `PromotionService` (to re-check eligibility server-side, never trusting a
 * submitted student id list alone) use. No pass/fail or results-based rule
 * of any kind — this milestone deliberately invents none. See
 * `docs/promotion.md`.
 *
 * Eligible means: the student's `StudentStatus` is `active`, **and** they
 * hold an `active` `Enrollment` for exactly this source session/level/arm —
 * a graduated or withdrawn student, or one already moved on to a different
 * class, is never eligible.
 */
class PromotionEligibilityService
{
    /**
     * @return Builder<Student>
     */
    public function eligibleQuery(AcademicSession $sourceSession, AcademicLevel $sourceLevel, ?LevelArm $sourceArm): Builder
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', function (Builder $q) use ($sourceSession, $sourceLevel, $sourceArm) {
                $q->where('academic_session_id', $sourceSession->getKey())
                    ->where('academic_level_id', $sourceLevel->getKey())
                    ->where('status', EnrollmentStatus::Active->value);

                if ($sourceArm !== null) {
                    $q->where('level_arm_id', $sourceArm->getKey());
                } else {
                    $q->whereNull('level_arm_id');
                }
            })
            ->ordered();
    }

    /**
     * @return Collection<int, Student>
     */
    public function eligibleStudents(AcademicSession $sourceSession, AcademicLevel $sourceLevel, ?LevelArm $sourceArm): Collection
    {
        return $this->eligibleQuery($sourceSession, $sourceLevel, $sourceArm)
            ->with(['currentEnrollment.level', 'currentEnrollment.arm'])
            ->get();
    }

    /**
     * Whether `$student` already holds *any* enrollment (of any status) for
     * the target session — the M21 spec's explicit "not already enrolled in
     * the target session" rule, checked independently of what M9's own
     * `Enrollment` model otherwise permits.
     */
    public function alreadyInTargetSession(Student $student, AcademicSession $targetSession): bool
    {
        return $student->enrollments()->where('academic_session_id', $targetSession->getKey())->exists();
    }
}
