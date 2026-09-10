<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\TimetableEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled lesson within a {@see Timetable}. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its timetable, so overlap checks are
 * tenant-safe without a join.
 *
 * `timetable_id` is set from the parent relation on create and never changes;
 * `school_id` is stamped from the tenant context. Times are `HH:MM` strings and
 * the interval is treated as **half-open** `[start_time, end_time)` — a lesson
 * ending at 10:00 does not clash with one starting at 10:00. Every scheduling
 * rule lives in `App\Http\Requests\Timetable\TimetableEntryRequest`; this model
 * only carries the shape.
 */
class TimetableEntry extends Model
{
    /** @use HasFactory<TimetableEntryFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'teacher_id',
        'weekday',
        'start_time',
        'end_time',
        'room',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
        ];
    }

    /**
     * @return BelongsTo<Timetable, $this>
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    /**
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    /**
     * @return BelongsTo<LevelArm, $this>
     */
    public function arm(): BelongsTo
    {
        return $this->belongsTo(LevelArm::class, 'level_arm_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Rows in $timetableId that share $weekday and overlap the half-open
     * interval [$start, $end). Tenant-scoped by {@see BelongsToSchool}.
     *
     * @param  Builder<TimetableEntry>  $query
     */
    public function scopeClashingWith(Builder $query, int $timetableId, int $weekday, string $start, string $end): void
    {
        $query->where($this->qualifyColumn('timetable_id'), $timetableId)
            ->where($this->qualifyColumn('weekday'), $weekday)
            ->where($this->qualifyColumn('start_time'), '<', $end)
            ->where($this->qualifyColumn('end_time'), '>', $start);
    }

    /**
     * @param  Builder<TimetableEntry>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('weekday'))
            ->orderBy($this->qualifyColumn('start_time'))
            ->orderBy($this->qualifyColumn('id'));
    }
}
