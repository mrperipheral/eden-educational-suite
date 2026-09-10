<?php

namespace App\Support\Timetable;

use App\Models\Timetable;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds scheduling clashes *within one timetable* — a teacher, a class/arm or a
 * room booked for two lessons that share a weekday and overlap in time (the
 * interval is half-open, so back-to-back lessons are fine).
 *
 * The scan is a single indexed self-join, tenant-scoped by the timetable id
 * (every entry of a tenant-resolved timetable is in that tenant) plus an
 * explicit `school_id` guard — it never loads entries into PHP. Per-entry checks
 * during create/edit live in `App\Http\Requests\Timetable\TimetableEntryRequest`;
 * this whole-timetable scan backs the publish guard and the grid warning banner.
 */
class TimetableConflictScanner
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** Whether the timetable can be published (has entries and no clashes). */
    public function isPublishable(Timetable $timetable): bool
    {
        return $timetable->entries()->exists() && $this->pairs($timetable)->isEmpty();
    }

    /**
     * The clashing lesson pairs, each `{ a_id, b_id, reason }` where reason is
     * one of `teacher` / `class` / `room`.
     *
     * @return Collection<int, object{a_id: int, b_id: int, reason: string}>
     */
    public function pairs(Timetable $timetable): Collection
    {
        $schoolId = $this->tenant->idOrFail();

        return DB::table('timetable_entries as a')
            ->join('timetable_entries as b', function ($join) {
                $join->on('a.timetable_id', '=', 'b.timetable_id')
                    ->on('a.weekday', '=', 'b.weekday')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->whereColumn('a.start_time', '<', 'b.end_time')
                    ->whereColumn('b.start_time', '<', 'a.end_time')
                    ->where(function ($q) {
                        $q->whereColumn('a.teacher_id', 'b.teacher_id')
                            ->orWhereColumn('a.level_arm_id', 'b.level_arm_id')
                            ->orWhereColumn('a.room', 'b.room'); // SQL NULL != NULL — unroomed lessons never clash on room
                    });
            })
            ->where('a.timetable_id', $timetable->getKey())
            ->where('a.school_id', $schoolId)
            ->orderBy('a.weekday')->orderBy('a.start_time')
            ->get(['a.id as a_id', 'b.id as b_id', 'a.teacher_id as a_teacher', 'b.teacher_id as b_teacher', 'a.level_arm_id as a_arm', 'b.level_arm_id as b_arm', 'a.room as a_room', 'b.room as b_room'])
            ->map(fn ($row) => (object) [
                'a_id' => (int) $row->a_id,
                'b_id' => (int) $row->b_id,
                'reason' => $this->reason($row),
            ])
            ->values();
    }

    private function reason(object $row): string
    {
        return match (true) {
            $row->a_teacher === $row->b_teacher => 'teacher',
            $row->a_arm === $row->b_arm => 'class',
            default => 'room',
        };
    }
}
