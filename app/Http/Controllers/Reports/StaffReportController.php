<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\StaffReport;
use App\Support\Csv\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teacher/staff (M11) reporting — a single aggregate summary page (counts,
 * status breakdown, subject/workload summary). Gated `reports.view` +
 * `staff.view`. No HR/payroll data anywhere here. See
 * `docs/reporting.md` §8.
 */
class StaffReportController extends Controller
{
    public function __construct(private readonly StaffReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('staff.view');

        return view('reports.staff.index', ['summary' => $this->report->summary()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('staff.view');

        $summary = $this->report->summary();
        $filename = 'staff-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total teachers', $summary['total']]);
            foreach ($summary['by_status'] as $status => $count) {
                fputcsv($handle, ["Status: {$status}", $count]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Subject', 'Active assignments']);
            foreach ($summary['by_subject'] as $row) {
                fputcsv($handle, CsvSanitizer::row([$row->subject?->name, $row->total]));
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Teacher', 'Active assignment count']);
            foreach ($summary['workload'] as $row) {
                fputcsv($handle, CsvSanitizer::row([$row->teacher?->fullName(), $row->assignment_count]));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
