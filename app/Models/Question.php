<?php

namespace App\Models;

use App\Enums\ExaminationQuestionType;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, tenant-scoped question (Milestone 23, `docs/cbt.md`) —
 * deliberately minimal groundwork for the future M24 Question Bank, not
 * the bank itself. Freely editable at any time; editing one **never**
 * changes an examination that already uses it, because
 * {@see ExaminationQuestion} snapshots its content at attach time and
 * never re-reads this row afterwards.
 *
 * `created_by` is not mass-assignable.
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'subject_id',
        'question_text',
        'marks',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExaminationQuestionType::class,
            'marks' => 'decimal:2',
            'is_active' => 'boolean',
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

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
