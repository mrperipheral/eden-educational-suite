<?php

namespace App\Models;

use App\Enums\TeacherAssignmentStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A teacher's teaching assignment: teach {@see Subject} to {@see AcademicLevel}
 * (optionally a {@see LevelArm}) for an {@see AcademicSession} (optionally an
 * {@see AcademicPeriod}). School-owned ({@see BelongsToSchool}) *and* scoped to
 * its teacher.
 *
 * `teacher_id` is set from the parent relation on create and never changes;
 * `school_id` is stamped from the tenant context. History is preserved — an
 * assignment that ends is marked `ended` ({@see self::end()}), never deleted.
 * The model is intentionally lean: the Timetable / Attendance / Assessment /
 * Results modules extend it later.
 */
class TeacherAssignment extends Model
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'status',
        'started_on',
        'ended_on',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TeacherAssignmentStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeacherAssignmentStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
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
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Close this assignment, keeping the row as history. */
    public function end(?Carbon $on = null): void
    {
        $this->status = TeacherAssignmentStatus::Ended;
        $this->ended_on ??= $on ?? Carbon::now();
        $this->save();
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), TeacherAssignmentStatus::Active->value);
    }

    /**
     * @param  Builder<TeacherAssignment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('started_on'))->orderByDesc($this->qualifyColumn('id'));
    }
}
