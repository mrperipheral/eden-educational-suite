<?php

namespace App\Reports;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\Module;
use App\Enums\Permission;
use App\Enums\ResultRunStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use App\Models\ExamAttempt;
use App\Models\Examination;
use App\Models\ResultRun;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\Modules\SchoolModules;

/**
 * KPI cards for the school dashboard (M27 §3). Every card is gated by
 * **both** its module (`SchoolModules::enabled()`) and the viewer's
 * existing domain permission — a disabled module or a lacking permission
 * simply omits that card entirely, never a misleading zero. Each card is a
 * small, fixed number of cheap indexed `COUNT`/`GROUP BY` queries (never a
 * loop over students/classes) — see `docs/reporting.md` §2.
 */
class DashboardReport
{
    public function __construct(
        private readonly SchoolModules $modules,
        private readonly FeeReport $feeReport,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function kpis(User $user): array
    {
        $cards = [];

        if ($this->modules->enabled(Module::Students) && $user->hasPermission(Permission::StudentView)) {
            $cards['students'] = $this->studentsCard();
        }

        if ($this->modules->enabled(Module::Staff) && $user->hasPermission(Permission::StaffView)) {
            $cards['staff'] = $this->staffCard();
        }

        if ($this->modules->enabled(Module::Attendance) && $user->hasPermission(Permission::AttendanceView)) {
            $cards['attendance'] = $this->attendanceCard();
        }

        if ($this->modules->enabled(Module::Results) && $user->hasPermission(Permission::ResultView)) {
            $cards['results'] = $this->resultsCard();
        }

        if ($this->modules->enabled(Module::Fees) && $user->hasPermission(Permission::FeesReport)) {
            $cards['fees'] = $this->feeReport->collectionSummary([]);
        }

        if ($this->modules->enabled(Module::Cbt) && $user->hasPermission(Permission::CbtView)) {
            $cards['cbt'] = $this->cbtCard();
        }

        return $cards;
    }

    /**
     * @return array{total:int, active:int, inactive:int, withdrawn:int, graduated:int}
     */
    private function studentsCard(): array
    {
        // ->toBase(): status is enum-cast on the model, and an enum
        // instance can't be a PHP array key — see CommunicationReport for
        // the full rationale, same fix applied throughout this class.
        $byStatus = Student::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus['active'] ?? 0),
            'inactive' => (int) ($byStatus['inactive'] ?? 0),
            'withdrawn' => (int) ($byStatus['withdrawn'] ?? 0),
            'graduated' => (int) ($byStatus['graduated'] ?? 0),
        ];
    }

    /**
     * @return array{total:int, active:int}
     */
    private function staffCard(): array
    {
        return [
            'total' => Teacher::query()->count(),
            'active' => Teacher::query()->where('status', 'active')->count(),
        ];
    }

    /**
     * Last 30 days of submitted registers only — a genuinely recent
     * snapshot, not a whole-history scan.
     *
     * @return array{registers_submitted:int, attendance_percentage:?float}
     */
    private function attendanceCard(): array
    {
        $row = AttendanceRecord::query()
            ->whereNotNull('status')
            ->whereHas('register', fn ($q) => $q->where('status', AttendanceRegisterStatus::Submitted->value)
                ->where('attendance_date', '>=', now()->subDays(30)->toDateString()))
            ->selectRaw('COUNT(*) as total_marks')
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as present_marks")
            ->first();

        $registersSubmitted = AttendanceRegister::query()
            ->where('status', AttendanceRegisterStatus::Submitted->value)
            ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
            ->count();

        return [
            'registers_submitted' => $registersSubmitted,
            'attendance_percentage' => ($row && $row->total_marks > 0) ? round($row->present_marks / $row->total_marks * 100, 2) : null,
        ];
    }

    /**
     * @return array{runs:int, published:int, locked:int}
     */
    private function resultsCard(): array
    {
        $byStatus = ResultRun::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');

        return [
            'runs' => (int) $byStatus->sum(),
            'published' => (int) ($byStatus[ResultRunStatus::Published->value] ?? 0),
            'locked' => (int) ($byStatus[ResultRunStatus::Locked->value] ?? 0),
        ];
    }

    /**
     * @return array{examinations:int, attempts:int, completed:int, passed:int}
     */
    private function cbtCard(): array
    {
        $byStatus = ExamAttempt::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status');
        $completed = (int) ($byStatus[ExamAttemptStatus::Completed->value] ?? 0);

        return [
            'examinations' => Examination::query()->count(),
            'attempts' => (int) $byStatus->sum(),
            'completed' => $completed,
            'passed' => $completed > 0 ? ExamAttempt::query()->where('status', ExamAttemptStatus::Completed->value)->where('passed', true)->count() : 0,
        ];
    }
}
