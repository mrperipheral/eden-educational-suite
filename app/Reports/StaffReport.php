<?php

namespace App\Reports;

use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Support\Collection;

/**
 * Teacher/staff (M11) administrative reporting — counts, status breakdown,
 * assignment/workload summary. "Workload" here means only *how many active
 * class assignments* a teacher currently holds — a scheduling/administrative
 * fact already stored on `TeacherAssignment`, never a payroll/HR metric
 * (M27 spec explicitly excludes HR/payroll analytics). See
 * `docs/reporting.md` §8.
 */
class StaffReport
{
    /**
     * @return array{total:int, by_status: array<string,int>, by_subject: Collection, workload: Collection}
     */
    public function summary(): array
    {
        // ->toBase(): status is enum-cast on the model, and an enum
        // instance can't be a PHP array key.
        $byStatus = Teacher::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status')->all();

        $bySubject = TeacherAssignment::query()
            ->active()
            ->selectRaw('subject_id, COUNT(*) as total')
            ->groupBy('subject_id')
            ->with('subject:id,name')
            ->get();

        $workload = TeacherAssignment::query()
            ->active()
            ->selectRaw('teacher_id, COUNT(*) as assignment_count')
            ->groupBy('teacher_id')
            ->with('teacher:id,first_name,middle_name,last_name,preferred_name')
            ->orderByDesc('assignment_count')
            ->limit(20)
            ->get();

        return [
            'total' => Teacher::query()->count(),
            'by_status' => $byStatus,
            'by_subject' => $bySubject,
            'workload' => $workload,
        ];
    }
}
