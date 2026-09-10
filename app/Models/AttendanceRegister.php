<?php

namespace App\Models;

use App\Enums\AttendanceRegisterStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AttendanceRegisterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An attendance register — one class's attendance for one day. School-owned
 * ({@see BelongsToSchool}): every query is constrained to the active tenant,
 * `school_id` is stamped from the context and is never in `$fillable` / read
 * from input. See `docs/attendance-management.md`.
 *
 * Tied to an {@see AcademicSession} (+ optional {@see AcademicPeriod}), a
 * {@see AcademicLevel} and a specific {@see LevelArm}, on `attendance_date`. It
 * does **not** depend on the timetable. `status` and `submitted_*` are **not**
 * mass-assignable — they change only through {@see self::submit()} /
 * {@see self::reopen()}.
 */
class AttendanceRegister extends Model
{
    /** @use HasFactory<AttendanceRegisterFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'attendance_date',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AttendanceRegisterStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceRegisterStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
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
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isLocked(): bool
    {
        return $this->status === AttendanceRegisterStatus::Submitted;
    }

    /**
     * The students eligible for this register — those with an {@see Enrollment}
     * for this exact (session, level, arm) whose date range contains the
     * register's date. The student's / enrollment's *current* status is not a
     * filter: a student withdrawn **after** the date was in class that day; one
     * whose enrollment ended **before** the date is excluded by the range.
     *
     * @return Builder<Student>
     */
    public function eligibleStudents(): Builder
    {
        $date = $this->attendance_date->toDateString();

        return Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q
                ->where('academic_session_id', $this->academic_session_id)
                ->where('academic_level_id', $this->academic_level_id)
                ->where('level_arm_id', $this->level_arm_id)
                ->where('started_on', '<=', $date)
                ->where(fn (Builder $q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $date)))
            ->ordered();
    }

    /**
     * Register-level totals, computed from the loaded `records` (no extra query
     * when eager-loaded).
     *
     * @return array{total: int, present: int, absent: int, late: int, excused: int, unmarked: int}
     */
    public function summary(): array
    {
        $records = $this->relationLoaded('records') ? $this->records : $this->records()->get();
        $byStatus = $records->countBy(fn (AttendanceRecord $r) => $r->status?->value ?? 'unmarked');

        return [
            'total' => $records->count(),
            'present' => $byStatus->get('present', 0),
            'absent' => $byStatus->get('absent', 0),
            'late' => $byStatus->get('late', 0),
            'excused' => $byStatus->get('excused', 0),
            'unmarked' => $byStatus->get('unmarked', 0),
        ];
    }

    /** Lock the register. Callers must confirm every record is marked first. */
    public function submit(User $by): void
    {
        DB::transaction(function () use ($by) {
            $this->status = AttendanceRegisterStatus::Submitted;
            $this->submitted_at = Carbon::now();
            $this->submitted_by = $by->getKey();
            $this->save();
        });
    }

    /** Return a submitted register to draft so an authorized user can correct it. */
    public function reopen(): void
    {
        DB::transaction(function () {
            $this->status = AttendanceRegisterStatus::Draft;
            $this->submitted_at = null;
            $this->submitted_by = null;
            $this->save();
        });
    }

    /**
     * @param  Builder<AttendanceRegister>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('attendance_date'))->orderByDesc($this->qualifyColumn('id'));
    }
}
