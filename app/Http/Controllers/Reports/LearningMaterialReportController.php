<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\LearningMaterialReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Learning Materials (M22) reporting — a single aggregate summary page.
 * Gated `reports.view` + `material.view`. No view/download analytics (M22
 * never built any) — see `docs/reporting.md` §11.
 */
class LearningMaterialReportController extends Controller
{
    public function __construct(private readonly LearningMaterialReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('material.view');

        return view('reports.learning-materials.index', ['summary' => $this->report->summary()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('material.view');

        $summary = $this->report->summary();
        $filename = 'learning-materials-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total materials', $summary['total']]);
            foreach ($summary['by_type'] as $type => $count) {
                fputcsv($handle, ["Type: {$type}", $count]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Subject', 'Materials']);
            foreach ($summary['by_subject'] as $row) {
                fputcsv($handle, [$row->subject?->name, $row->total]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
