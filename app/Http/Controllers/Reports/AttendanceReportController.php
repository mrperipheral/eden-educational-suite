<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Reports\Concerns\BuildsReportFilterOptions;
use App\Models\User;
use App\Reports\AttendanceReport;
use App\Support\Csv\CsvSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attendance (M13) reports — student attendance, class/arm attendance,
 * date-based trend — one page with a `?tab=` switch
 * (student|class|trend). Gated `reports.view` + `attendance.view`; a
 * Teacher without `attendance.manage` sees only their own assigned
 * classes. See `docs/reporting.md` §4.
 */
class AttendanceReportController extends Controller
{
    use BuildsReportFilterOptions;

    public function __construct(private readonly AttendanceReport $report) {}

    public function index(Request $request): View
    {
        $this->authorize('reports.view');
        $this->authorize('attendance.view');

        $tab = $request->string('tab', 'student')->value();
        $filters = $this->filters($request);
        $user = $request->user();

        $data = match ($tab) {
            'class' => ['classAttendance' => $this->report->classAttendance($filters, $user)],
            'trend' => ['trend' => $this->report->trend($filters, $user)],
            default => ['studentAttendance' => $this->report->studentAttendance($filters, $user)],
        };

        return view('reports.attendance.index', [
            'tab' => in_array($tab, ['student', 'class', 'trend'], true) ? $tab : 'student',
            'filters' => $request->only(['session', 'period', 'level', 'arm', 'from', 'to']),
            ...$this->academicFilterOptions(),
            ...$data,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');
        $this->authorize('attendance.view');

        $tab = $request->string('tab', 'student')->value();
        $filters = $this->filters($request);
        $user = $request->user();
        $filename = 'attendance-'.$tab.'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tab, $filters, $user) {
            $handle = fopen('php://output', 'w');

            match ($tab) {
                'class' => $this->exportClassAttendance($handle, $filters, $user),
                'trend' => $this->exportTrend($handle, $filters, $user),
                default => $this->exportStudentAttendance($handle, $filters, $user),
            };

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  resource  $handle
     */
    private function exportStudentAttendance($handle, array $filters, User $user): void
    {
        fputcsv($handle, ['Student', 'Admission number', 'Days marked', 'Present', 'Absent', 'Late', 'Excused']);

        $this->report->studentAttendanceQuery($filters, $user)->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $row) {
                fputcsv($handle, CsvSanitizer::row([
                    $row->student?->fullName(),
                    $row->student?->admission_number,
                    $row->days_marked,
                    $row->days_present,
                    $row->days_absent,
                    $row->days_late,
                    $row->days_excused,
                ]));
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    private function exportClassAttendance($handle, array $filters, User $user): void
    {
        fputcsv($handle, ['Level', 'Arm', 'Registers submitted', 'Total marks', 'Present marks', 'Absence marks', 'Attendance %']);

        foreach ($this->report->classAttendance($filters, $user) as $row) {
            fputcsv($handle, CsvSanitizer::row([
                $row['level_name'], $row['arm_name'], $row['registers_submitted'],
                $row['total_marks'], $row['present_marks'], $row['absence_marks'], $row['attendance_percentage'],
            ]));
        }
    }

    /**
     * @param  resource  $handle
     */
    private function exportTrend($handle, array $filters, User $user): void
    {
        fputcsv($handle, ['Date', 'Total marks', 'Present marks', 'Attendance %']);

        foreach ($this->report->trend($filters, $user) as $row) {
            fputcsv($handle, CsvSanitizer::row([$row['attendance_date'], $row['total_marks'], $row['present_marks'], $row['attendance_percentage']]));
        }
    }

    /**
     * @return array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'session' => $request->integer('session') ?: null,
            'period' => $request->integer('period') ?: null,
            'level' => $request->integer('level') ?: null,
            'arm' => $request->integer('arm') ?: null,
            'from' => $request->string('from')->value() ?: null,
            'to' => $request->string('to')->value() ?: null,
        ]);
    }
}
