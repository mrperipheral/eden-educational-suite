<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ExamAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question's answer within one {@see ExamAttempt} (Milestone 23,
 * `docs/cbt.md` §9). Every row is bulk-inserted, unanswered
 * (`selected_option_id` null), the moment the attempt starts — one per
 * {@see ExaminationQuestion} — mirroring M13's `AttendanceRecord`
 * roster-snapshot convention, so "answered" is always a plain
 * `whereNotNull('selected_option_id')` check.
 *
 * `is_correct`/`marks_awarded` are **not** mass-assignable — computed only
 * at marking time by comparing `selected_option_id` against the
 * snapshotted `ExaminationQuestionOption.is_correct`, never trusted from
 * client input.
 */
class ExamAnswer extends Model
{
    /** @use HasFactory<ExamAnswerFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'exam_attempt_id',
        'examination_question_id',
        'selected_option_id',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'is_correct' => 'boolean',
            'marks_awarded' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * @return BelongsTo<ExaminationQuestion, $this>
     */
    public function examinationQuestion(): BelongsTo
    {
        return $this->belongsTo(ExaminationQuestion::class);
    }

    /**
     * @return BelongsTo<ExaminationQuestionOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ExaminationQuestionOption::class, 'selected_option_id');
    }

    public function isAnswered(): bool
    {
        return $this->selected_option_id !== null;
    }
}
