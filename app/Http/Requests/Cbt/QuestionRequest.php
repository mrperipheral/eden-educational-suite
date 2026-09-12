<?php

namespace App\Http\Requests\Cbt;

use App\Enums\ExaminationQuestionType;
use App\Enums\Permission;
use App\Models\Question;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit a reusable {@see Question}, together with its
 * options, in one submission. Gated `cbt.author` or `cbt.manage` (the
 * question bank itself is not class-scoped, so no further authorizer check
 * is needed beyond the coarse permission — only *attaching* a question to
 * a specific exam is class/subject-scoped, via `App\Support\Cbt\
 * CbtAuthorizer`).
 *
 * "Exactly one correct option" and the type-appropriate option count
 * (exactly two for true/false, two or more for multiple choice) are
 * application-layer invariants checked in {@see self::withValidator()} —
 * not something a DB constraint expresses cleanly, mirroring
 * `grading_scheme_grades`' non-overlap rule.
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
            'question_text' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(ExaminationQuestionType::class)],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.option_text' => ['required', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();

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
            'question_text' => $this->string('question_text')->trim()->value(),
            'marks' => (float) $this->input('marks'),
            'is_active' => $this->boolean('is_active', true),
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
