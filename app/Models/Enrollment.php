<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A student's placement in a level/arm for a session (and optionally a period).
 * School-owned ({@see BelongsToSchool}) *and* scoped to its student.
 *
 * `student_id` is set from the parent relation on create and never changes;
 * `school_id` is stamped from the tenant context. History is preserved — a
 * student who changes class gets a new row and the old one is closed, never
 * deleted.
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'status',
        'started_on',
        'ended_on',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => EnrollmentStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
     * Make this the student's current placement, closing any other open
     * enrollment they have (`completed`, `ended_on` filled). The queries are
     * tenant-scoped by {@see SchoolScope} and further limited to this student.
     * This is a "one class at a time" invariant — not a promotion workflow.
     */
    public function makeActive(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('student_id', $this->student_id)
                ->whereKeyNot($this->getKey())
                ->where('status', EnrollmentStatus::Active->value)
                ->get()
                ->each(function (self $sibling): void {
                    $sibling->status = EnrollmentStatus::Completed;
                    $sibling->ended_on ??= $this->started_on;
                    $sibling->save();
                });

            $this->status = EnrollmentStatus::Active;
            $this->ended_on = null;
            $this->save();
        });
    }

    /**
     * @param  Builder<Enrollment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), EnrollmentStatus::Active->value);
    }

    /**
     * @param  Builder<Enrollment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('started_on'))->orderByDesc($this->qualifyColumn('id'));
    }
}
