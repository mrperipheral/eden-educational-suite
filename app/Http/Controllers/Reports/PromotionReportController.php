<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Reports\PromotionReport;
use App\Support\Csv\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Promotion & Graduation (M21) reports — promotion batch history and
 * graduation history, one page with a `?tab=` switch (batches|graduation).
 * Gated `reports.view` + `promotion.view`. No placement-recommendation
 * logic — see `docs/reporting.md` §10.
 */
class PromotionReportController extends Controller
{
    public function __construct(private readonly PromotionReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('promotion.view');

        $tab = $request->string('tab', 'batches')->value();
        $filters = array_filter(['session' => $request->integer('session') ?: null]);

        $data = $tab === 'graduation'
            ? ['graduationHistory' => $this->report->graduationHistory($filters)]
            : ['batches' => $this->report->batches($filters)];

        return view('reports.promotion.index', [
            'tab' => $tab === 'graduation' ? 'graduation' : 'batches',
            'filters' => $request->only(['session']),
            'sessions' => AcademicSession::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'graduatedCount' => $this->report->graduatedCount(),
            ...$data,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('promotion.view');

        $tab = $request->string('tab', 'batches')->value();
        $filters = array_filter(['session' => $request->integer('session') ?: null]);
        $filename = 'promotion-'.$tab.'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tab, $filters) {
            $handle = fopen('php://output', 'w');

            if ($tab === 'graduation') {
                fputcsv($handle, ['Student', 'Admission number', 'Graduated on', 'Session', 'Notes']);
                $this->report->graduationHistoryQuery($filters)->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $student) {
                        fputcsv($handle, CsvSanitizer::row([$student->fullName(), $student->admission_number, $student->graduated_at?->toDateString(), $student->graduatedSession?->name, $student->graduation_notes]));
                    }
                });
            } else {
                fputcsv($handle, ['Source', 'Target', 'Status', 'Students', 'Created by', 'Created at']);
                $this->report->batchesQuery($filters)->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $batch) {
                        fputcsv($handle, CsvSanitizer::row([
                            trim(($batch->sourceLevel?->name ?? '').' '.($batch->sourceArm?->name ?? '').' ('.($batch->sourceSession?->name ?? '').')'),
                            trim(($batch->targetLevel?->name ?? '').' '.($batch->targetArm?->name ?? '').' ('.($batch->targetSession?->name ?? '').')'),
                            $batch->status?->label(),
                            $batch->records_count,
                            $batch->createdBy?->name,
                            $batch->created_at?->toDateTimeString(),
                        ]));
                    }
                });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
