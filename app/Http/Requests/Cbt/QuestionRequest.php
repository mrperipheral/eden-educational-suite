<?php

namespace App\Http\Requests\Cbt;

use App\Enums\ExaminationQuestionType;
use App\Enums\Permission;
use App\Enums\QuestionDifficulty;
use App\Models\LevelArm;
use App\Models\Question;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Create or edit a reusable {@see Question}, together with its
 * options, in one submission. The coarse gate is holding `cbt.author` or
 * `cbt.manage`; the controller re-checks the exact subject/level/arm via
 * `App\Support\Cbt\CbtAuthorizer::canManageQuestionFor()` (never trusted
 * from this request alone) — a Teacher without `.manage` may only create/
 * edit a question for a subject/level/arm they hold an active M11
 * `TeacherAssignment` for (M24, `docs/question-bank.md`).
 *
 * `academic_level_id`/`level_arm_id` are optional — a question may stay
 * subject-only. "Exactly one correct option" and the type-appropriate
 * option count (exactly two for true/false, two or more for multiple
 * choice) are application-layer invariants checked in
 * {@see self::withValidator()} — not something a DB constraint expresses
 * cleanly, mirroring `grading_scheme_grades`' non-overlap rule.
 */
class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CbtAuthor)
            || $this->user()?->hasPermission(Permission::CbtManage)
            ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where('school_id', $schoolId),
            ],
            'academic_level_id' => [
                'nullable', 'integer',
                Rule::exists('academic_levels', 'id')->where('school_id', $schoolId),
            ],
            'level_arm_id' => [
                'nullable', 'integer',
                Rule::exists('level_arms', 'id')->where('school_id', $schoolId),
            ],
            'question_text' => ['required', 'string', 'max:2000'],
            'topic' => ['nullable', 'string', 'max:150'],
            'type' => ['required', Rule::enum(ExaminationQuestionType::class)],
            'difficulty' => ['required', Rule::enum(QuestionDifficulty::class)],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.option_text' => ['required', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            $schoolId = app(TenantContext::class)->idOrFail();

            $levelId = $this->input('academic_level_id');
            $armId = $this->input('level_arm_id');

            if ($armId && ! $levelId && ! $errors->has('level_arm_id')) {
                $errors->add('level_arm_id', __('Select a level before choosing an arm.'));
            }

            if ($armId && $levelId && ! $errors->has('level_arm_id') && ! $errors->has('academic_level_id')
                && ! LevelArm::query()->whereKey($armId)->where('academic_level_id', $levelId)->exists()) {
                $errors->add('level_arm_id', __('That arm is not part of the selected level.'));
            }

            $subjectId = $this->input('subject_id');
            if ($levelId && $subjectId && ! $errors->has('subject_id') && ! $errors->has('academic_level_id')
                && ! DB::table('level_subject')->where('school_id', $schoolId)->where('academic_level_id', $levelId)->where('subject_id', $subjectId)->exists()) {
                $errors->add('subject_id', __('That subject is not offered by the selected level.'));
            }

            if ($errors->has('options') || $errors->hasAny(array_keys($this->input('options', [])))) {
                return;
            }

            $type = ExaminationQuestionType::tryFrom((string) $this->input('type'));
            $options = collect($this->input('options', []));

            if ($type !== null) {
                $required = $type->fixedOptionCount();
                if ($required !== null && $options->count() !== $required) {
                    $errors->add('options', __('A :type question needs exactly :count options.', ['type' => $type->label(), 'count' => $required]));
                }
            }

            $correctCount = $options->filter(fn ($o) => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();
            if ($correctCount !== 1) {
                $errors->add('options', __('Exactly one option must be marked correct.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'subject_id' => (int) $this->input('subject_id'),
            'academic_level_id' => $this->filled('academic_level_id') ? (int) $this->input('academic_level_id') : null,
            'level_arm_id' => $this->filled('level_arm_id') ? (int) $this->input('level_arm_id') : null,
            'question_text' => $this->string('question_text')->trim()->value(),
            'topic' => $this->filled('topic') ? $this->string('topic')->trim()->value() : null,
            'marks' => (float) $this->input('marks'),
        ];
    }

    /**
     * @return list<array{option_text: string, is_correct: bool}>
     */
    public function options(): array
    {
        return collect($this->input('options', []))
            ->map(fn ($o) => [
                'option_text' => trim((string) ($o['option_text'] ?? '')),
                'is_correct' => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
    }
}
