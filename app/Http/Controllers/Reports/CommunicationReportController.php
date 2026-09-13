<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\CommunicationReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Communication (M18) reporting — a single aggregate summary page. Gated
 * `reports.view` + `communication.view`. No BI system around
 * communications — see `docs/reporting.md` §12.
 */
class CommunicationReportController extends Controller
{
    public function __construct(private readonly CommunicationReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('communication.view');

        return view('reports.communication.index', ['summary' => $this->report->summary()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('communication.view');

        $summary = $this->report->summary();
        $filename = 'communication-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total threads', $summary['threads_total']]);
            foreach ($summary['threads_by_status'] as $status => $count) {
                fputcsv($handle, ["Status: {$status}", $count]);
            }
            foreach ($summary['threads_by_category'] as $category => $count) {
                fputcsv($handle, ["Category: {$category}", $count]);
            }
            fputcsv($handle, ['Announcements published', $summary['announcements_published']]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
