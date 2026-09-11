<?php

namespace App\Services\Fees;

use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a manual {@see FeePayment} and (optionally) allocates it across
 * one or more of the student's {@see StudentFeeCharge} rows, all inside a
 * single DB transaction. See `docs/fees.md`.
 *
 * Every guard the M19 spec requires lives here, not in a controller:
 * negative/zero amounts, over-allocating a single charge past its own
 * outstanding balance, over-allocating a payment past its own total, and
 * allocating to a charge that is not this payment's own student's. Charges
 * are row-locked (`lockForUpdate`) for the duration of the transaction so two
 * concurrent allocations against the same charge can't both succeed.
 */
class FeePaymentService
{
    /**
     * @param  array{amount:string, payment_date:string, reference:string, method:string, payer_name:?string, payer_phone:?string, payer_email:?string, notes:?string}  $data
     * @param  array<int, string>  $allocations  charge id => amount to allocate
     */
    public function record(Student $student, array $data, array $allocations, User $by): FeePayment
    {
        return DB::transaction(function () use ($student, $data, $allocations, $by) {
            $payment = new FeePayment([...$data, 'student_id' => $student->getKey()]);
            $payment->recorded_by = $by->getKey();
            $payment->save();

            if ($allocations !== []) {
                $this->allocate($payment, $allocations, $by);
            }

            return $payment;
        });
    }

    /**
     * @param  array<int, string>  $allocations  charge id => amount to allocate
     */
    public function allocate(FeePayment $payment, array $allocations, User $by): void
    {
        DB::transaction(function () use ($payment, $allocations, $by) {
            abort_if($payment->isVoided(), 422, 'Cannot allocate a voided payment.');

            $chargeIds = array_map('intval', array_keys($allocations));
            $charges = StudentFeeCharge::query()->whereKey($chargeIds)->lockForUpdate()->get()->keyBy('id');

            $totalNewAllocation = '0.00';

            foreach ($allocations as $chargeId => $amount) {
                $amount = (string) $amount;

                if (bccomp($amount, '0.00', 2) <= 0) {
                    throw new \InvalidArgumentException('Allocation amount must be greater than zero.');
                }

                $charge = $charges->get((int) $chargeId);

                if ($charge === null || $charge->student_id !== $payment->student_id) {
                    throw new \DomainException("Charge #{$chargeId} does not belong to this payment's student.");
                }

                if (bccomp($amount, $charge->outstandingBalance(), 2) === 1) {
                    throw new \DomainException("Allocation exceeds charge #{$chargeId}'s outstanding balance.");
                }

                $totalNewAllocation = bcadd($totalNewAllocation, $amount, 2);
            }

            $newTotal = bcadd($payment->allocatedAmount(), $totalNewAllocation, 2);

            if (bccomp($newTotal, (string) $payment->amount, 2) === 1) {
                throw new \DomainException('Allocation exceeds the payment amount.');
            }

            foreach ($allocations as $chargeId => $amount) {
                $allocation = new FeePaymentAllocation([
                    'fee_payment_id' => $payment->getKey(),
                    'student_fee_charge_id' => (int) $chargeId,
                    'amount' => (string) $amount,
                ]);
                $allocation->created_by = $by->getKey();
                $allocation->save();
            }
        });
    }
}
