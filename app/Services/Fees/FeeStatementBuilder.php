<?php

namespace App\Services\Fees;

use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use Illuminate\Support\Collection;

/**
 * Builds a student's fee statement — every charge, every payment, and
 * server-calculated totals. The single seam the staff fee-management screens
 * *and* the Parent/Student portals use, so a statement can never drift
 * between audiences (the M16 `ReportCardRenderer` pattern). See
 * `docs/fees.md`.
 *
 * Everything is eager-loaded once (`charges.allocations.payment`,
 * `payments.allocations.charge`) so rendering a statement with many
 * transactions never runs a query per row, and every total is summed with
 * `bcmath` over the already-loaded rows — never a native float, never an
 * Eloquent `sum()` (which casts through float).
 */
class FeeStatementBuilder
{
    /**
     * @return array{
     *     charges: Collection<int, StudentFeeCharge>,
     *     payments: Collection<int, FeePayment>,
     *     totalCharged: string,
     *     totalDiscount: string,
     *     totalWaived: string,
     *     totalPaid: string,
     *     totalOutstanding: string,
     *     totalReceived: string,
     *     totalUnallocated: string,
     * }
     */
    public function statementFor(Student $student): array
    {
        $charges = StudentFeeCharge::query()
            ->forStudent($student)
            ->with([
                'category:id,name',
                'session:id,name',
                'period:id,name',
                'allocations.payment:id,voided_at',
            ])
            ->ordered()
            ->get();

        $payments = FeePayment::query()
            ->forStudent($student)
            ->with(['allocations.charge:id,description', 'recordedBy:id,name'])
            ->ordered()
            ->get();

        return [
            'charges' => $charges,
            'payments' => $payments,
            'totalCharged' => $this->sum($charges, fn (StudentFeeCharge $c) => (string) $c->amount),
            'totalDiscount' => $this->sum($charges, fn (StudentFeeCharge $c) => (string) $c->discount_amount),
            'totalWaived' => $this->sum($charges, fn (StudentFeeCharge $c) => $c->waivedAmount()),
            'totalPaid' => $this->sum($charges, fn (StudentFeeCharge $c) => $c->allocatedAmount()),
            'totalOutstanding' => $this->sum($charges, fn (StudentFeeCharge $c) => $c->outstandingBalance()),
            'totalReceived' => $this->sum($payments->filter(fn (FeePayment $p) => ! $p->isVoided()), fn (FeePayment $p) => (string) $p->amount),
            'totalUnallocated' => $this->sum($payments->filter(fn (FeePayment $p) => ! $p->isVoided()), fn (FeePayment $p) => $p->unallocatedAmount()),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function sum(Collection $items, \Closure $getter): string
    {
        return $items->reduce(fn (string $carry, mixed $item) => bcadd($carry, $getter($item), 2), '0.00');
    }
}
