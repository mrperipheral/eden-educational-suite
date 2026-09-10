<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AssessmentScoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's score in an {@see Assessment}. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its assessment.
 *
 * `assessment_id` is set from the parent relation on create and never changes;
 * `school_id` is stamped from the tenant context. `score` is **nullable** — a
 * null means the student has not been marked yet. The valid range
 * (`0 <= score <= assessment.max_score`, 2 dp) is enforced in the Form Request,
 * not by a DB constraint. Scores are never deleted for historical reasons.
 *
 * No grade / percentage / rank is stored here — that is M15's to derive.
 */
class AssessmentScore extends Model
{
    /** @use HasFactory<AssessmentScoreFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'score',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
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

    public function isEntered(): bool
    {
        return $this->score !== null;
    }

    /**
     * @param  Builder<AssessmentScore>  $query
     */
    public function scopeUnentered(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('score'));
    }
}
