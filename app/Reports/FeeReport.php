<?php

namespace App\Reports;

use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\StudentFeeCharge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fees (M19) + Paystack (M20) reporting — collection summary, outstanding
 * balances, payment activity. Every total is computed via SQL `SUM()` over
 * `student_fee_charges`/`fee_payment_allocations`/`fee_payments`, never a
 * PHP loop over `FeeStatementBuilder::statementFor()` per student (that
 * would be one query set per student — fine for a single statement page,
 * not for a school-wide aggregate). `FeePayment::notVoided()` is always
 * applied; `bcmath` throughout, never a float sum. See `docs/reporting.md`
 * §5. Reachable only by `fees.report` holders (School Admin, Principal,
 * Bursar) — no Teacher-scoping exists for fee data anywhere in this app,
 * so none is added here.
 */
class FeeReport
{
    /**
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     * @return array{total_charged:string, total_discount:string, total_waived:string, total_collected:string, total_outstanding:string}
     */
    public function collectionSummary(array $filters): array
    {
        $charges = $this->chargeQuery($filters)
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as total_discount')
            ->selectRaw('COALESCE(SUM(CASE WHEN waived_at IS NOT NULL THEN amount - discount_amount ELSE 0 END), 0) as total_waived')
            ->first();

        $allocatedToNonWaived = $this->allocatedAmountQuery(fn (Builder $q) => $this->applyChargeFilters($q->whereNull('waived_at'), $filters))->value('total') ?? '0';

        $payableTotal = bcsub((string) $charges->total_amount, (string) $charges->total_discount, 2);
        $outstanding = bcsub(bcsub($payableTotal, (string) $charges->total_waived, 2), (string) $allocatedToNonWaived, 2);

        $collected = $this->paymentQuery($filters)->selectRaw('COALESCE(SUM(amount), 0) as total')->value('total') ?? '0';

        return [
            'total_charged' => number_format((float) $charges->total_amount, 2, '.', ''),
            'total_discount' => number_format((float) $charges->total_discount, 2, '.', ''),
            'total_waived' => number_format((float) $charges->total_waived, 2, '.', ''),
            'total_collected' => number_format((float) $collected, 2, '.', ''),
            'total_outstanding' => number_format(max(0.0, (float) $outstanding), 2, '.', ''),
        ];
    }

    /**
     * Per-student outstanding balance — paginated, only students with a
     * balance greater than zero.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     */
    public function outstandingBalances(array $filters): LengthAwarePaginator
    {
        $page = $this->outstandingBalancesQuery($filters)->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn ($row) => $this->withOutstandingBalance($row));

        return $page;
    }

    /**
     * The exact per-student charge aggregate `outstandingBalances()`
     * paginates. The allocated-amount per charge is folded in as a
     * correlated subquery (never a per-row/per-student follow-up query) so
     * the `HAVING` clause can filter to a genuinely positive balance in the
     * database, not in PHP after pagination — see `docs/reporting.md` §5.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     * @return Builder<StudentFeeCharge>
     */
    public function outstandingBalancesQuery(array $filters): Builder
    {
        // Non-voided allocations against this one charge — correlated to the
        // outer `student_fee_charges` row per group member, not looped in PHP.
        $allocatedToCharge = '(SELECT COALESCE(SUM(fpa.amount), 0) FROM fee_payment_allocations fpa '
            .'JOIN fee_payments fp ON fp.id = fpa.fee_payment_id '
            .'WHERE fpa.student_fee_charge_id = student_fee_charges.id AND fp.voided_at IS NULL)';

        return $this->chargeQuery($filters)
            ->select('student_id')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('SUM(discount_amount) as total_discount')
            ->selectRaw('SUM(CASE WHEN waived_at IS NOT NULL THEN amount - discount_amount ELSE 0 END) as total_waived')
            ->selectRaw("SUM(CASE WHEN waived_at IS NULL THEN amount - discount_amount - {$allocatedToCharge} ELSE 0 END) as total_outstanding")
            ->groupBy('student_id')
            ->havingRaw('total_outstanding > 0')
            ->with('student:id,first_name,middle_name,last_name,preferred_name,admission_number')
            ->orderBy('student_id');
    }

    /**
     * Streams every outstanding-balance row for export — `chunk()`s the
     * grouped charge query (never loads the whole set at once).
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     * @param  callable(object):void  $rowCallback
     */
    public function exportOutstandingBalances(array $filters, callable $rowCallback): void
    {
        $this->outstandingBalancesQuery($filters)->chunk(200, function ($chunk) use ($rowCallback) {
            foreach ($chunk as $row) {
                $rowCallback($this->withOutstandingBalance($row));
            }
        });
    }

    private function withOutstandingBalance(object $row): object
    {
        $row->outstanding_balance = number_format(max(0.0, (float) $row->total_outstanding), 2, '.', '');

        return $row;
    }

    /**
     * A plain, filterable, paginated list of recorded payments — includes
     * Paystack-originated ones (`method = paystack`) using the exact same
     * `FeePayment` rows the statement/history already show, never a
     * separate calculation. `FeePayment` carries no session/period/level/
     * arm of its own (that context lives on the charges it's allocated
     * against) — filterable only by `method`/date range here.
     *
     * @param  array{method?:string,from?:string,to?:string}  $filters
     */
    public function paymentActivity(array $filters): LengthAwarePaginator
    {
        return $this->paymentActivityQuery($filters)->paginate(25)->withQueryString();
    }

    /**
     * The exact query `paymentActivity()` paginates — exposed for the
     * export action to `chunk()`.
     *
     * @param  array{method?:string,from?:string,to?:string}  $filters
     * @return Builder<FeePayment>
     */
    public function paymentActivityQuery(array $filters): Builder
    {
        return $this->paymentQuery($filters)
            ->with('student:id,first_name,middle_name,last_name,preferred_name,admission_number')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    /**
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     * @return Builder<StudentFeeCharge>
     */
    private function chargeQuery(array $filters): Builder
    {
        return $this->applyChargeFilters(StudentFeeCharge::query(), $filters);
    }

    /**
     * @param  Builder<StudentFeeCharge>  $query
     * @param  array{session?:int,period?:int,level?:int,arm?:int}  $filters
     * @return Builder<StudentFeeCharge>
     */
    private function applyChargeFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['session'])) {
            $query->where('academic_session_id', $filters['session']);
        }
        if (! empty($filters['period'])) {
            $query->where('academic_period_id', $filters['period']);
        }
        if (! empty($filters['level'])) {
            $query->where('academic_level_id', $filters['level']);
        }
        if (! empty($filters['arm'])) {
            $query->where('level_arm_id', $filters['arm']);
        }

        return $query;
    }

    /**
     * @param  array{method?:string,from?:string,to?:string}  $filters
     * @return Builder<FeePayment>
     */
    private function paymentQuery(array $filters): Builder
    {
        $query = FeePayment::query()->notVoided();

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('payment_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('payment_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  callable(Builder<StudentFeeCharge>):Builder<StudentFeeCharge>  $chargeScope
     */
    private function allocatedAmountQuery(callable $chargeScope): Builder
    {
        return FeePaymentAllocation::query()
            ->whereHas('payment', fn (Builder $q) => $q->notVoided())
            ->whereHas('charge', $chargeScope)
            ->selectRaw('COALESCE(SUM(amount), 0) as total');
    }
}
