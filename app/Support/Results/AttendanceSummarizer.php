<?php

namespace App\Support\Results;

use App\Enums\AttendanceRegisterStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegister;
use Illuminate\Support\Collection;

/**
 * A per-student attendance snapshot for a term, from M13 data — "days school
 * opened / present / absent / attendance %". See
 * `docs/results-report-cards.md` §"Report card — attendance". No new
 * attendance table is introduced; this only reads {@see AttendanceRegister} /
 * {@see AttendanceRecord}.
 *
 * Scoped to `(academic_session, academic_period)` only — not the result run's
 * arm — so a student who changed class mid-term still gets their whole term's
 * attendance, not just what happened in their current class. Only
 * **submitted** (locked) registers count; a student's `days_opened` is however
 * many of those registers they have a record in, which naturally accounts for
 * joining mid-term (no M13 registers existed for them before that).
 *
 * Batched — one query for the register ids, one for every student's records —
 * never one query per student.
 */
final class AttendanceSummarizer
{
    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{opened: int, present: int, absent: int, percentage: float|null}>
     */
    public static function summarize(array $studentIds, int $academicSessionId, int $academicPeriodId): array
    {
        if ($studentIds === []) {
            return [];
        }

        $registerIds = AttendanceRegister::query()
            ->where('academic_session_id', $academicSessionId)
            ->where('academic_period_id', $academicPeriodId)
            ->where('status', AttendanceRegisterStatus::Submitted->value)
            ->pluck('id');

        $empty = ['opened' => 0, 'present' => 0, 'absent' => 0, 'percentage' => null];

        if ($registerIds->isEmpty()) {
            return array_fill_keys($studentIds, $empty);
        }

        /** @var Collection<int, AttendanceRecord> $records */
        $records = AttendanceRecord::query()
            ->whereIn('attendance_register_id', $registerIds)
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $out = [];

        foreach ($studentIds as $studentId) {
            $mine = $records->get($studentId, collect());
            $opened = $mine->count();
            $present = $mine->filter(fn (AttendanceRecord $r) => $r->status?->isAttending() === true)->count();

            $out[$studentId] = $opened === 0 ? $empty : [
                'opened' => $opened,
                'present' => $present,
                'absent' => $opened - $present,
                'percentage' => round($present / $opened * 100, 2),
            ];
        }

        return $out;
    }
}
