<?php

namespace App\Models;

use App\Enums\ExaminationQuestionType;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A reusable, tenant-scoped Question Bank entry (Milestone 24,
 * `docs/question-bank.md`; introduced minimally in M23,
 * `docs/cbt.md` §5). `academic_level_id`/`level_arm_id` are **optional** —
 * a question may stay subject-only and reusable across every level of
 * that subject, or be scoped to one specific class.
 *
 * Freely editable at any time; editing one **never** changes an
 * examination that already uses it, because {@see ExaminationQuestion}
 * snapshots its content at attach time and never re-reads this row
 * afterwards — true independent of `status`, so archiving/deactivating a
 * question a live or historical exam already uses changes nothing about
 * that exam.
 *
 * `type`/`difficulty`/`status`/`created_by` are **not** mass-assignable.
 * `status` changes only through {@see self::activate()} /
 * {@see self::deactivate()} / {@see self::archive()} — only `Active`
 * questions may be **newly** attached to an exam
 * ({@see QuestionStatus::isSelectable()}).
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'subject_id',
        'academic_level_id',
        'level_arm_id',
        'question_text',
        'topic',
        'marks',
    ];

    /**
     * `status`/`difficulty` default to their DB-column defaults (`active`/
     * `medium`) — set here too so a freshly-`new`-ed instance has them
     * available in memory immediately, without a round-trip reload,
     * mirroring `App\Models\Examination`'s own convention.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'difficulty' => 'medium',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExaminationQuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'status' => QuestionStatus::class,
            'marks' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('position');
    }

    public function isSelectable(): bool
    {
        return $this->status->isSelectable();
    }

    public function activate(): void
    {
        DB::transaction(function () {
            $this->status = QuestionStatus::Active;
            $this->save();
        });
    }

    public function deactivate(): void
    {
        DB::transaction(function () {
            $this->status = QuestionStatus::Inactive;
            $this->save();
        });
    }

    public function archive(): void
    {
        DB::transaction(function () {
            $this->status = QuestionStatus::Archived;
            $this->save();
        });
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), QuestionStatus::Active->value);
    }

    /**
     * Compatible with a `(subject, level, arm)` context — used to filter
     * the CBT attach picker (M23 §6, M24 integration): the question's own
     * `academic_level_id` is either null (usable at any level of that
     * subject) or an exact match; if it further specifies `level_arm_id`,
     * that must match too (a level-only question — `level_arm_id` null —
     * is usable in any arm of that level).
     *
     * @param  Builder<Question>  $query
     */
    public function scopeCompatibleWith(Builder $query, int $subjectId, int $levelId, ?int $armId): void
    {
        $query->where($this->qualifyColumn('subject_id'), $subjectId)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('academic_level_id'))->orWhere($this->qualifyColumn('academic_level_id'), $levelId))
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('level_arm_id'))->orWhere($this->qualifyColumn('level_arm_id'), $armId));
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
