<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ExaminationQuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One snapshotted option for an {@see ExaminationQuestion} (Milestone 23,
 * `docs/cbt.md` §4, §6). `is_correct` is the marking key —
 * `App\Services\Cbt\ExamAttemptService` is the only code that ever reads
 * it; it is never serialised into a student-facing response.
 */
class ExaminationQuestionOption extends Model
{
    /** @use HasFactory<ExaminationQuestionOptionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'examination_question_id',
        'option_text',
        'is_correct',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ExaminationQuestion, $this>
     */
    public function examinationQuestion(): BelongsTo
    {
        return $this->belongsTo(ExaminationQuestion::class);
    }
}
