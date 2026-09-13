<?php

namespace App\Reports;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Student (M9) enrollment reporting — status/level/arm/gender breakdowns.
 * Aggregate-only: the existing `/students` list already provides the raw
 * per-student roster, so this never re-lists individual students, only
 * counts. See `docs/reporting.md` §7.
 */
class StudentReport
{
    /**
     * @param  array{level?:int,arm?:int}  $filters
     * @return array{total:int, by_status: array<string,int>, by_gender: array<string,int>, by_level: Collection, by_arm: Collection}
     */
    public function enrollmentSummary(array $filters): array
    {
        $studentQuery = Student::query();
        if (! empty($filters['level']) || ! empty($filters['arm'])) {
            $studentQuery->whereHas('currentEnrollment', function ($q) use ($filters) {
                if (! empty($filters['level'])) {
                    $q->where('academic_level_id', $filters['level']);
                }
                if (! empty($filters['arm'])) {
                    $q->where('level_arm_id', $filters['arm']);
                }
            });
        }

        // ->toBase(): status/gender are enum-cast on the model, and an
        // enum instance can't be a PHP array key.
        $byStatus = (clone $studentQuery)->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->pluck('total', 'status')->all();
        $byGender = (clone $studentQuery)->whereNotNull('gender')->selectRaw('gender, COUNT(*) as total')->groupBy('gender')->toBase()->pluck('total', 'gender')->all();

        $enrollmentQuery = Enrollment::query()->active();
        if (! empty($filters['level'])) {
            $enrollmentQuery->where('academic_level_id', $filters['level']);
        }
        if (! empty($filters['arm'])) {
            $enrollmentQuery->where('level_arm_id', $filters['arm']);
        }

        $byLevel = (clone $enrollmentQuery)
            ->selectRaw('academic_level_id, COUNT(*) as total')
            ->groupBy('academic_level_id')
            ->with('level:id,name')
            ->get();

        $byArm = (clone $enrollmentQuery)
            ->whereNotNull('level_arm_id')
            ->selectRaw('academic_level_id, level_arm_id, COUNT(*) as total')
            ->groupBy('academic_level_id', 'level_arm_id')
            ->with(['level:id,name', 'arm:id,name'])
            ->get();

        return [
            'total' => (clone $studentQuery)->count(),
            'by_status' => $byStatus,
            'by_gender' => $byGender,
            'by_level' => $byLevel,
            'by_arm' => $byArm,
        ];
    }
}
