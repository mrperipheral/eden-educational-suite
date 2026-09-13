<?php

namespace App\Http\Controllers\Reports;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\BuildsReportFilterOptions;
use App\Models\Examination;
use App\Reports\CbtReport;
use App\Support\Reports\ReportAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CBT (M23) + Question Bank (M24) reports — examination summary and one
 * exam's attempt list. Staff-only (gated `reports.view` + `cbt.view`); a
 * Teacher without `cbt.manage` sees only examinations for classes/subjects
 * they teach. Never exposes correct answers; staff see attempt scores
 * unconditionally (matches the existing M23 staff attempt list — see
 * `docs/reporting.md` §9).
 */
class CbtReportController extends Controller
{
    use BuildsReportFilterOptions;

    public function __construct(private readonly CbtReport $report, private readonly ReportAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('cbt.view');

        $filters = $this->filters($request);
        $user = $request->user();

        return view('reports.cbt.index', [
            'filters' => $request->only(['session', 'period', 'level', 'arm', 'subject', 'status']),
            ...$this->academicFilterOptions(),
            'examinations' => $this->report->examinationSummary($filters, $user),
        ]);
    }

    public function attempts(Request $request, int $examination): View
    {
        $this->authorize('reports.view');
        $this->authorize('cbt.view');

        $exam = Examination::query()->with(['session:id,name', 'level:id,name', 'arm:id,name', 'subject:id,name'])->findOrFail($examination);

        // A Teacher without cbt.manage may only drill into attempts for an
        // exam matching their own active assignments — the same
        // level+subject scoping `CbtReport::examinationSummary()` already
        // applies to the list itself.
        $user = $request->user();
        if (! $user->hasPermission(Permission::CbtManage)) {
            $levelMatches = in_array($exam->academic_level_id, $this->authorizer->levelIdsFor($user), true);
            $subjectMatches = in_array($exam->subject_id, $this->authorizer->subjectIdsFor($user), true);
            abort_unless($levelMatches && $subjectMatches, 403);
        }

        return view('reports.cbt.attempts', [
            'examination' => $exam,
            'attempts' => $this->report->attempts($exam),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('cbt.view');

        $filters = $this->filters($request);
        $user = $request->user();
        $filename = 'cbt-examination-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters, $user) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Title', 'Session', 'Level', 'Arm', 'Subject', 'Status', 'Attempts', 'Completed', 'Passed', 'Average %']);

            $this->report->examinationSummaryQuery($filters, $user)->chunk(200, function ($chunk) use ($handle) {
                foreach ($chunk as $exam) {
                    fputcsv($handle, [
                        $exam->title, $exam->session?->name, $exam->level?->name, $exam->arm?->name, $exam->subject?->name,
                        $exam->status?->label(), $exam->attempts_count, $exam->completed_attempts_count, $exam->passed_attempts_count,
                        $exam->average_percentage !== null ? round((float) $exam->average_percentage, 2) : null,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{session?:int,period?:int,level?:int,arm?:int,subject?:int,status?:string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'session' => $request->integer('session') ?: null,
            'period' => $request->integer('period') ?: null,
            'level' => $request->integer('level') ?: null,
            'arm' => $request->integer('arm') ?: null,
            'subject' => $request->integer('subject') ?: null,
            'status' => $request->string('status')->value() ?: null,
        ]);
    }
}
