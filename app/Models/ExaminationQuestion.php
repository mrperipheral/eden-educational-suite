<?php

namespace App\Models;

use App\Enums\ExaminationQuestionType;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ExaminationQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One question **snapshot** attached to an {@see Examination} (Milestone
 * 23, `docs/cbt.md` §6). `question_text`/`type`/`marks` are copied from
 * the source {@see Question} at attach time and never re-read from it —
 * editing or deleting the source question afterwards never changes this
 * row. `question` is a soft traceability link only (nullable).
 */
class ExaminationQuestion extends Model
{
    /** @use HasFactory<ExaminationQuestionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'examination_id',
        'question_id',
        'question_text',
        'marks',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExaminationQuestionType::class,
            'marks' => 'decimal:2',
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
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasMany<ExaminationQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ExaminationQuestionOption::class)->orderBy('position');
    }
}
