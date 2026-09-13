<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\BuildsReportFilterOptions;
use App\Reports\AcademicReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Academic (M15 results) reports — student performance, subject
 * performance, class/arm performance, result-run summary, one page with a
 * `?tab=` switch (student|subject|class|runs). Gated `reports.view` +
 * `result.view` — a Teacher without `result.manage` sees only their own
 * assigned classes/subjects (`AcademicReport`'s own teacher-scoping). See
 * `docs/reporting.md` §3.
 */
class AcademicReportController extends Controller
{
    use BuildsReportFilterOptions;

    public function __construct(private readonly AcademicReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('result.view');

        $tab = $request->string('tab', 'runs')->value();
        $filters = $this->filters($request);
        $user = $request->user();

        $data = match ($tab) {
            'student' => ['studentResults' => $this->report->studentPerformance($filters, $user)],
            'subject' => ['subjectResults' => $this->report->subjectPerformance($filters, $user)],
            'class' => ['classResults' => $this->report->classPerformance($filters, $user)],
            default => ['runs' => $this->report->resultRunSummary($filters, $user)],
        };

        return view('reports.academic.index', [
            'tab' => in_array($tab, ['student', 'subject', 'class', 'runs'], true) ? $tab : 'runs',
            'filters' => $request->only(['session', 'period', 'level', 'arm', 'run', 'status']),
            ...$this->academicFilterOptions(),
            ...$data,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('result.view');

        $tab = $request->string('tab', 'runs')->value();
        $filters = $this->filters($request);
        $user = $request->user();
        $filename = 'academic-'.$tab.'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tab, $filters, $user) {
            $handle = fopen('php://output', 'w');

            match ($tab) {
                'student' => $this->exportStudentPerformance($handle, $filters, $user),
                'subject' => $this->exportSubjectPerformance($handle, $filters, $user),
                'class' => $this->exportClassPerformance($handle, $filters, $user),
                default => $this->exportRunSummary($handle, $filters, $user),
            };

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  resource  $handle
     */
    private function exportStudentPerformance($handle, array $filters, $user): void
    {
        fputcsv($handle, ['Student', 'Admission number', 'Level', 'Arm', 'Average %', 'Position', 'Class size', 'Grade']);

        $this->report->studentPerformanceQuery($filters, $user)->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $row) {
                fputcsv($handle, [
                    $row->student?->fullName(),
                    $row->student?->admission_number,
                    $row->academic_level_id,
                    $row->level_arm_id,
                    $row->average_percentage,
                    $row->position,
                    $row->class_size,
                    $row->overall_grade_code_snapshot,
                ]);
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    private function exportSubjectPerformance($handle, array $filters, $user): void
    {
        fputcsv($handle, ['Subject', 'Students assessed', 'Average %', 'Grade distribution']);

        foreach ($this->report->subjectPerformance($filters, $user) as $row) {
            fputcsv($handle, [
                $row['subject_name'],
                $row['students_assessed'],
                $row['average_percentage'],
                $this->formatGradeDistribution($row['grades']),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function exportClassPerformance($handle, array $filters, $user): void
    {
        fputcsv($handle, ['Level', 'Arm', 'Students', 'Average %', 'Grade distribution']);

        foreach ($this->report->classPerformance($filters, $user) as $row) {
            fputcsv($handle, [
                $row['level_name'],
                $row['arm_name'],
                $row['student_count'],
                $row['average_percentage'],
                $this->formatGradeDistribution($row['grades']),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    private function exportRunSummary($handle, array $filters, $user): void
    {
        fputcsv($handle, ['Session', 'Period', 'Level', 'Arm', 'Status', 'Students']);

        $this->report->resultRunSummaryQuery($filters, $user)->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $row) {
                fputcsv($handle, [
                    $row->session?->name,
                    $row->period?->name,
                    $row->level?->name,
                    $row->arm?->name,
                    $row->status?->label(),
                    $row->student_results_count,
                ]);
            }
        });
    }

    /**
     * @param  array<string,int>  $grades
     */
    private function formatGradeDistribution(array $grades): string
    {
        $parts = [];
        foreach ($grades as $code => $count) {
            $parts[] = "{$code}:{$count}";
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{session?:int,period?:int,level?:int,arm?:int,run?:int,status?:string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'session' => $request->integer('session') ?: null,
            'period' => $request->integer('period') ?: null,
            'level' => $request->integer('level') ?: null,
            'arm' => $request->integer('arm') ?: null,
            'run' => $request->integer('run') ?: null,
            'status' => $request->string('status')->value() ?: null,
        ]);
    }
}
