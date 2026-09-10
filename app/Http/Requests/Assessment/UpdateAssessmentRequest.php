<?php

namespace App\Http\Requests\Assessment;

use App\Models\Assessment;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Edit an assessment. Only allowed while it is a **draft** — the academic
 * context is fixed at creation, so this touches the title / category / maximum
 * score / instructions only. The maximum score cannot be dropped below a score
 * already recorded.
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

            $highest = $this->assessment()?->highestRecordedScore();
            if ($highest !== null && (float) $this->input('max_score') < $highest) {
                $validator->errors()->add('max_score', __('A score of :n is already recorded — the maximum cannot be lower.', ['n' => $highest]));
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
