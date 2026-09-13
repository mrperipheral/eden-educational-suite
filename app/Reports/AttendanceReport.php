<?php

namespace App\Reports;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\Permission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use App\Models\User;
use App\Support\Reports\ReportAuthorizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Attendance (M13) reporting — student attendance, class/arm attendance,
 * and a date-based trend. M13 itself deferred analytics entirely
 * (`docs/attendance-management.md` §"Deferred"); this is the first place
 * that data is aggregated. Only `submitted` (locked) registers are ever
 * counted — an in-progress draft register is not yet a fact, mirroring
 * `AttendanceSummarizer`'s own convention. "Present" always means
 * `AttendanceStatus::isAttending()` (Present + Late), the same rule used
 * everywhere else attendance is summarised. See `docs/reporting.md` §4.
 */
class AttendanceReport
{
    public function __construct(private readonly ReportAuthorizer $authorizer) {}

    /**
     * Per-student counts across the filtered date range/class — paginated.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}  $filters
     */
    public function studentAttendance(array $filters, User $user): LengthAwarePaginator
    {
        return $this->studentAttendanceQuery($filters, $user)->paginate(25)->withQueryString();
    }

    /**
     * The exact query `studentAttendance()` paginates — exposed for the
     * export action to `chunk()` instead of loading every row at once.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}  $filters
     * @return Builder<AttendanceRecord>
     */
    public function studentAttendanceQuery(array $filters, User $user): Builder
    {
        return AttendanceRecord::query()
            ->whereNotNull('status')
            ->whereHas('register', function (Builder $q) use ($filters, $user) {
                $q->where('status', AttendanceRegisterStatus::Submitted->value);
                $this->applyRegisterFilters($q, $filters);
                $this->scopeToTeacher($q, $user);
            })
            ->selectRaw('student_id, COUNT(*) as days_marked')
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as days_present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as days_absent")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as days_late")
            ->selectRaw("SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as days_excused")
            ->groupBy('student_id')
            ->with('student:id,first_name,middle_name,last_name,preferred_name,admission_number')
            ->orderBy('student_id');
    }

    /**
     * Per-class/arm aggregate: registers submitted, average attendance %.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}  $filters
     * @return Collection<int, array{academic_level_id:int, level_arm_id:int, level_name:?string, arm_name:?string, registers_submitted:int, total_marks:int, present_marks:int, absence_marks:int, attendance_percentage:?float}>
     */
    public function classAttendance(array $filters, User $user): Collection
    {
        $query = AttendanceRegister::query()
            ->where('status', AttendanceRegisterStatus::Submitted->value)
            ->with(['level:id,name', 'arm:id,name']);

        $this->applyRegisterFilters($query, $filters);
        $this->scopeToTeacher($query, $user);

        $registers = $query->withCount([
            'records as total_marks' => fn (Builder $q) => $q->whereNotNull('status'),
            'records as present_marks' => fn (Builder $q) => $q->whereIn('status', ['present', 'late']),
        ])->get();

        return $registers
            ->groupBy(fn (AttendanceRegister $r) => $r->academic_level_id.'-'.$r->level_arm_id)
            ->map(function (Collection $group) {
                $first = $group->first();
                $totalMarks = $group->sum('total_marks');
                $presentMarks = $group->sum('present_marks');

                return [
                    'academic_level_id' => $first->academic_level_id,
                    'level_arm_id' => $first->level_arm_id,
                    'level_name' => $first->level?->name,
                    'arm_name' => $first->arm?->name,
                    'registers_submitted' => $group->count(),
                    'total_marks' => $totalMarks,
                    'present_marks' => $presentMarks,
                    'absence_marks' => $totalMarks - $presentMarks,
                    'attendance_percentage' => $totalMarks > 0 ? round($presentMarks / $totalMarks * 100, 2) : null,
                ];
            })
            ->sortByDesc('attendance_percentage')
            ->values();
    }

    /**
     * Date-based trend: one row per attendance date in range, with the
     * school-wide (or filtered class's) attendance percentage that day.
     *
     * @param  array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}  $filters
     * @return Collection<int, array{attendance_date:string, total_marks:int, present_marks:int, attendance_percentage:?float}>
     */
    public function trend(array $filters, User $user): Collection
    {
        // A direct join (register filters applied against the qualified
        // `attendance_registers.*` columns) rather than a `whereHas`
        // subquery — both `AttendanceRecord` and `AttendanceRegister` stamp
        // their own `SchoolScope` qualified by table name
        // (`getQualifiedSchoolIdColumn()`), so the join never produces an
        // ambiguous `school_id` reference.
        $query = AttendanceRecord::query()
            ->whereNotNull('attendance_records.status')
            ->join('attendance_registers', 'attendance_registers.id', '=', 'attendance_records.attendance_register_id')
            ->where('attendance_registers.status', AttendanceRegisterStatus::Submitted->value);

        $this->applyRegisterFilters($query, $filters, 'attendance_registers.');
        $this->scopeToTeacher($query, $user, 'attendance_registers.academic_level_id');

        $query
            ->selectRaw('attendance_registers.attendance_date as attendance_date')
            ->selectRaw('COUNT(*) as total_marks')
            ->selectRaw("SUM(CASE WHEN attendance_records.status IN ('present', 'late') THEN 1 ELSE 0 END) as present_marks")
            ->groupBy('attendance_registers.attendance_date')
            ->orderBy('attendance_registers.attendance_date');

        return $query->get()->map(fn ($row) => [
            'attendance_date' => (string) $row->attendance_date,
            'total_marks' => (int) $row->total_marks,
            'present_marks' => (int) $row->present_marks,
            'attendance_percentage' => $row->total_marks > 0 ? round($row->present_marks / $row->total_marks * 100, 2) : null,
        ]);
    }

    /**
     * @param  Builder<AttendanceRegister>|Builder<AttendanceRecord>  $query
     * @param  array{session?:int,period?:int,level?:int,arm?:int,from?:string,to?:string}  $filters
     */
    private function applyRegisterFilters(Builder $query, array $filters, string $prefix = ''): void
    {
        if (! empty($filters['session'])) {
            $query->where($prefix.'academic_session_id', $filters['session']);
        }
        if (! empty($filters['period'])) {
            $query->where($prefix.'academic_period_id', $filters['period']);
        }
        if (! empty($filters['level'])) {
            $query->where($prefix.'academic_level_id', $filters['level']);
        }
        if (! empty($filters['arm'])) {
            $query->where($prefix.'level_arm_id', $filters['arm']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate($prefix.'attendance_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate($prefix.'attendance_date', '<=', $filters['to']);
        }
    }

    /**
     * A Teacher without `attendance.manage` sees only the classes they
     * hold an active M11 assignment for.
     *
     * @param  Builder<AttendanceRegister>|Builder<AttendanceRecord>  $query
     */
    private function scopeToTeacher(Builder $query, User $user, string $levelColumn = 'academic_level_id'): void
    {
        if ($user->hasPermission(Permission::AttendanceManage)) {
            return;
        }

        $levelIds = $this->authorizer->levelIdsFor($user);

        if ($levelIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($levelColumn, $levelIds);
    }
}
