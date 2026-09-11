<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\StudentResultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's overall result within a {@see ResultRun}. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its run. Written only by
 * `App\Services\Results\ResultCompiler` — see `docs/results-report-cards.md`.
 *
 * `class_teacher_comment` / `principal_comment` are the only fields a human
 * ever edits directly (through a dedicated endpoint gated `result.enter` /
 * `result.manage`, class-scoped for a teacher exactly like M13/M14). Every
 * other column is compiled and, once the parent run is approved, frozen.
 */
class StudentResult extends Model
{
    /** @use HasFactory<StudentResultFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'total_percentage',
        'average_percentage',
        'class_teacher_comment',
        'principal_comment',
        'subject_count',
        'overall_grade_code_snapshot',
        'overall_grade_remark_snapshot',
        'position',
        'class_size',
        'days_school_opened',
        'days_present',
        'days_absent',
        'attendance_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_percentage' => 'decimal:2',
            'average_percentage' => 'decimal:2',
            'subject_count' => 'integer',
            'position' => 'integer',
            'class_size' => 'integer',
            'days_school_opened' => 'integer',
            'days_present' => 'integer',
            'days_absent' => 'integer',
            'attendance_percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ResultRun, $this>
     */
    public function resultRun(): BelongsTo
    {
        return $this->belongsTo(ResultRun::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hasAttendanceData(): bool
    {
        return $this->days_school_opened !== null;
    }

    /**
     * @param  Builder<StudentResult>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw($this->qualifyColumn('position').' is null')
            ->orderBy($this->qualifyColumn('position'))
            ->orderByDesc($this->qualifyColumn('average_percentage'));
    }
}
