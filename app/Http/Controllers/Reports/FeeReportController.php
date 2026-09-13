<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\BuildsReportFilterOptions;
use App\Reports\FeeReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fees (M19) + Paystack (M20) reports — collection summary, outstanding
 * balances, payment activity — one page with a `?tab=` switch
 * (summary|outstanding|payments). Gated `reports.view` + `fees.report`
 * (School Admin/Principal/Bursar only — no Teacher access to financial
 * data anywhere in this app). See `docs/reporting.md` §5.
 */
class FeeReportController extends Controller
{
    use BuildsReportFilterOptions;

    public function __construct(private readonly FeeReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('fees.report');

        $tab = $request->string('tab', 'summary')->value();
        $filters = $this->filters($request);

        $data = match ($tab) {
            'outstanding' => ['outstanding' => $this->report->outstandingBalances($filters)],
            'payments' => ['payments' => $this->report->paymentActivity($filters)],
            default => ['summary' => $this->report->collectionSummary($filters)],
        };

        return view('reports.fees.index', [
            'tab' => in_array($tab, ['summary', 'outstanding', 'payments'], true) ? $tab : 'summary',
            'filters' => $request->only(['session', 'period', 'level', 'arm', 'method', 'from', 'to']),
            ...$this->academicFilterOptions(),
            ...$data,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('fees.report');

        $tab = $request->string('tab', 'summary')->value();
        $filters = $this->filters($request);
        $filename = 'fees-'.$tab.'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tab, $filters) {
            $handle = fopen('php://output', 'w');

            match ($tab) {
                'outstanding' => $this->exportOutstanding($handle, $filters),
                'payments' => $this->exportPayments($handle, $filters),
                default => $this->exportSummary($handle, $filters),
            };

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  resource  $handle
     */
    private function exportSummary($handle, array $filters): void
    {
        $summary = $this->report->collectionSummary($filters);
        fputcsv($handle, ['Total charged', 'Total discount', 'Total waived', 'Total collected', 'Total outstanding']);
        fputcsv($handle, [$summary['total_charged'], $summary['total_discount'], $summary['total_waived'], $summary['total_collected'], $summary['total_outstanding']]);
    }

    /**
     * @param  resource  $handle
     */
    private function exportOutstanding($handle, array $filters): void
    {
        fputcsv($handle, ['Student', 'Admission number', 'Total charged', 'Discount', 'Waived', 'Outstanding balance']);

        $this->report->exportOutstandingBalances($filters, function ($row) use ($handle) {
            fputcsv($handle, [
                $row->student?->fullName(),
                $row->student?->admission_number,
                $row->total_amount,
                $row->total_discount,
                $row->total_waived,
                $row->outstanding_balance,
            ]);
        });
    }

    /**
     * @param  resource  $handle
     */
    private function exportPayments($handle, array $filters): void
    {
        fputcsv($handle, ['Date', 'Student', 'Admission number', 'Amount', 'Method', 'Reference']);

        $this->report->paymentActivityQuery($filters)->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $payment) {
                fputcsv($handle, [
                    $payment->payment_date?->toDateString(),
                    $payment->student?->fullName(),
                    $payment->student?->admission_number,
                    $payment->amount,
                    $payment->method?->label(),
                    $payment->reference,
                ]);
            }
        });
    }

    /**
     * @return array{session?:int,period?:int,level?:int,arm?:int,method?:string,from?:string,to?:string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'session' => $request->integer('session') ?: null,
            'period' => $request->integer('period') ?: null,
            'level' => $request->integer('level') ?: null,
            'arm' => $request->integer('arm') ?: null,
            'method' => $request->string('method')->value() ?: null,
            'from' => $request->string('from')->value() ?: null,
            'to' => $request->string('to')->value() ?: null,
        ]);
    }
}
