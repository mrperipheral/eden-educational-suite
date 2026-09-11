<?php

namespace App\Http\Requests\Assessment;

use App\Models\Assessment;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Edit an assessment. Only allowed while it is a **draft** — the academic
 * context is fixed at creation, so this touches the title / category / maximum
 * score / instructions only. The maximum score is **frozen** once any score has
 * been recorded — changing it (up or down) would silently rescale every score
 * already entered against it.
 */
class UpdateAssessmentRequest extends AssessmentModuleRequest
{
    private ?Assessment $assessment = null;

    public function authorize(): bool
    {
        $assessment = $this->assessment();

        if ($assessment === null || ! $assessment->structureEditable()) {
            return false;
        }

        return app(AssessmentAuthorizer::class)->canRecordFor(
            $this->user(),
            $assessment->academic_level_id,
            $assessment->level_arm_id,
            $assessment->subject_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();

        return [
            'assessment_category_id' => [
                'required', 'integer',
                Rule::exists('assessment_categories', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:150'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,2'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assessment = $this->assessment();

            // Once any score is recorded the maximum is frozen: raising or
            // lowering it would silently rescale every score already entered
            // against it. Clear the scores first to change it.
            if ($assessment !== null
                && $assessment->highestRecordedScore() !== null
                && (float) $this->input('max_score') !== (float) $assessment->max_score) {
                $validator->errors()->add('max_score', __('A score is already recorded — clear the scores before changing the maximum.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->assessment() === null) {
            abort(404);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assessment_category_id.exists' => __('The selected category is invalid.'),
            'max_score.decimal' => __('The maximum score may have at most two decimal places.'),
            'max_score.gt' => __('The maximum score must be greater than zero.'),
        ];
    }

    public function assessment(): ?Assessment
    {
        return $this->assessment ??= Assessment::query()->find($this->route('assessment'));
    }
}
