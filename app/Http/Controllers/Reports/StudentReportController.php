<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\BuildsReportFilterOptions;
use App\Reports\StudentReport;
use App\Support\Csv\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student (M9) enrollment reporting — a single aggregate summary page
 * (status/level/arm/gender breakdowns). Gated `reports.view` +
 * `student.view`. See `docs/reporting.md` §7.
 */
class StudentReportController extends Controller
{
    use BuildsReportFilterOptions;

    public function __construct(private readonly StudentReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('student.view');

        $filters = $this->filters($request);

        return view('reports.students.index', [
            'filters' => $request->only(['level', 'arm']),
            ...$this->academicFilterOptions(),
            'summary' => $this->report->enrollmentSummary($filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('student.view');

        $summary = $this->report->enrollmentSummary($this->filters($request));
        $filename = 'student-enrollment-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($summary) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total students', $summary['total']]);
            foreach ($summary['by_status'] as $status => $count) {
                fputcsv($handle, ["Status: {$status}", $count]);
            }
            foreach ($summary['by_gender'] as $gender => $count) {
                fputcsv($handle, ["Gender: {$gender}", $count]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Level', 'Students']);
            foreach ($summary['by_level'] as $row) {
                fputcsv($handle, CsvSanitizer::row([$row->level?->name, $row->total]));
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Level', 'Arm', 'Students']);
            foreach ($summary['by_arm'] as $row) {
                fputcsv($handle, CsvSanitizer::row([$row->level?->name, $row->arm?->name, $row->total]));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{level?:int,arm?:int}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'level' => $request->integer('level') ?: null,
            'arm' => $request->integer('arm') ?: null,
        ]);
    }
}
