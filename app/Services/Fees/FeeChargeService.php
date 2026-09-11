<?php

namespace App\Services\Fees;

use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;

/**
 * Creates a {@see StudentFeeCharge} — either snapshotted from a
 * {@see FeeStructure} or entered manually. Either way, `amount` and every
 * context column are copied onto the charge at this moment: a later edit to
 * the fee structure, or a class change, never touches the charge again. See
 * `docs/fees.md`.
 *
 * A charge always carries the student's **current** enrollment's level/arm —
 * never a client-supplied one — so a charge always reflects the class the
 * student was actually in when it was raised.
 */
class FeeChargeService
{
    /** @throws \DomainException when the student has no current enrollment */
    public function createFromStructure(Student $student, FeeStructure $structure, User $by, ?string $description = null): StudentFeeCharge
    {
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null) {
            throw new \DomainException('This student has no current class enrollment to charge against.');
        }

        $charge = new StudentFeeCharge([
            'student_id' => $student->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'fee_structure_id' => $structure->getKey(),
            'fee_category_id' => $structure->fee_category_id,
            'academic_session_id' => $structure->academic_session_id,
            'academic_period_id' => $structure->academic_period_id,
            'academic_level_id' => $enrollment->academic_level_id,
            'level_arm_id' => $enrollment->level_arm_id,
            'description' => $description ?: $structure->category->name,
            'amount' => (string) $structure->amount,
        ]);
        $charge->created_by = $by->getKey();
        $charge->save();

        return $charge;
    }

    /**
     * @param  array{fee_category_id:int, academic_session_id:int, academic_period_id:?int, description:string, amount:string}  $data
     *
     * @throws \DomainException when the student has no current enrollment
     */
    public function createManual(Student $student, array $data, User $by): StudentFeeCharge
    {
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null) {
            throw new \DomainException('This student has no current class enrollment to charge against.');
        }

        $charge = new StudentFeeCharge([
            'student_id' => $student->getKey(),
            'enrollment_id' => $enrollment->getKey(),
            'fee_category_id' => $data['fee_category_id'],
            'academic_session_id' => $data['academic_session_id'],
            'academic_period_id' => $data['academic_period_id'] ?? null,
            'academic_level_id' => $enrollment->academic_level_id,
            'level_arm_id' => $enrollment->level_arm_id,
            'description' => $data['description'],
            'amount' => $data['amount'],
        ]);
        $charge->created_by = $by->getKey();
        $charge->save();

        return $charge;
    }
}
