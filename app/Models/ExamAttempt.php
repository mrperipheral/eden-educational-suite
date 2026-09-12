<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\ResultReleaseMode;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One student's timed attempt at an {@see Examination} (Milestone 23,
 * `docs/cbt.md` §7–8). School-owned **and** student-scoped.
 * `unique(examination_id, student_id)` at the DB level is the real
 * guarantee behind "one attempt per student per examination" — not just an
 * application check.
 *
 * `expires_at` is computed once at `started_at + duration_minutes` and
 * never recalculated — the sole server-side timing authority; nothing
 * about a client-submitted duration or the browser's own clock is ever
 * trusted. `status`/`score`/`max_score`/`percentage`/`passed`/
 * `submitted_at`/`auto_submitted` are **not** mass-assignable — written
 * only by `App\Services\Cbt\ExamAttemptService`.
 */
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'examination_id',
        'student_id',
        'started_at',
        'expires_at',
    ];

    /**
     * `status` defaults to its DB-column default (`in_progress`) — set
     * here too so a freshly-`new`-ed instance has it available in memory
     * immediately, without a round-trip reload, mirroring
     * `App\Models\TeacherAssignment`'s own convention.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExamAttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'auto_submitted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<ExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExamAttemptStatus::Completed;
    }

    /** Whether the server clock has passed this attempt's own deadline. */
    public function isExpired(?Carbon $now = null): bool
    {
        return ($now ?? Carbon::now())->gte($this->expires_at);
    }

    /**
     * Whether the score/percentage/pass-fail may be shown to the student
     * who sat it — never governs whether correct answers are exposed
     * (M23 never exposes those to a student at all).
     */
    public function isResultVisible(?Carbon $now = null): bool
    {
        if (! $this->isCompleted()) {
            return false;
        }

        $examination = $this->relationLoaded('examination') ? $this->examination : $this->examination()->first();

        if ($examination->result_release === ResultReleaseMode::Immediate) {
            return true;
        }

        return $examination->result_release_at !== null
            && ($now ?? Carbon::now())->gte($examination->result_release_at);
    }

    /**
     * @param  Builder<ExamAttempt>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('started_at'));
    }
}
