<?php

namespace App\Models;

use App\Enums\AssignmentSubmissionStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Whether one student has turned in an {@see Assignment}. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its assignment.
 *
 * `status` ({@see AssignmentSubmissionStatus}) defaults to `pending`. This is
 * completion tracking only — it holds **no score**. Rows are never deleted for
 * historical reasons.
 */
class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'status',
        'submitted_on',
        'remark',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AssignmentSubmissionStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentSubmissionStatus::class,
            'submitted_on' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
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
     * @param  Builder<AssignmentSubmission>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), AssignmentSubmissionStatus::Pending->value);
    }
}
