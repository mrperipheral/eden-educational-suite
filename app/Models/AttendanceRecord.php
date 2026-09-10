<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's mark within an {@see AttendanceRegister}. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its register.
 *
 * `attendance_register_id` is set from the parent relation on create and never
 * changes; `school_id` is stamped from the tenant context. `status` is nullable
 * — a null means the student has not been marked yet, and the register cannot be
 * submitted while any record is unmarked. Records are never deleted for
 * historical reasons (see `docs/attendance-management.md`).
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'status',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AttendanceRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(AttendanceRegister::class, 'attendance_register_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<AttendanceRecord>  $query
     */
    public function scopeUnmarked(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('status'));
    }
}
