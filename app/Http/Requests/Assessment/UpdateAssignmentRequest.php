<?php

namespace App\Http\Requests\Assessment;

use App\Models\Assignment;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;

/**
 * Edit an assignment. Allowed only while it is a **draft** — the academic
 * context is fixed at creation, so this touches the title / instructions /
 * dates / maximum score only.
 */
class UpdateAssignmentRequest extends AssessmentModuleRequest
{
    private ?Assignment $assignment = null;

    public function authorize(): bool
    {
        $assignment = $this->assignment();

        if ($assignment === null || ! $assignment->structureEditable()) {
            return false;
        }

        return app(AssessmentAuthorizer::class)->canRecordFor(
            $this->user(),
            $assignment->academic_level_id,
            $assignment->level_arm_id,
            $assignment->subject_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'assigned_on' => ['required', 'date', 'after:2000-01-01'],
            'due_on' => ['required', 'date', 'after_or_equal:assigned_on'],
            'max_score' => ['nullable', 'numeric', 'gt:0', 'max:100000', 'decimal:0,2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assignment = $this->assignment();
            $session = $assignment->session;
            $assigned = (string) $this->input('assigned_on');
            $due = (string) $this->input('due_on');

            if ($assigned < $session->starts_on->toDateString() || $assigned > $session->ends_on->toDateString()) {
                $validator->errors()->add('assigned_on', __('That date is outside the assignment\'s session.'));
            }

            if ($due > $session->ends_on->toDateString()) {
                $validator->errors()->add('due_on', __('The due date is outside the assignment\'s session.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->assignment() === null) {
            abort(404);
        }

        if ($this->input('max_score') === '') {
            $this->merge(['max_score' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_on.after_or_equal' => __('The due date cannot be before the assigned date.'),
            'max_score.decimal' => __('The maximum score may have at most two decimal places.'),
        ];
    }

    public function assignment(): ?Assignment
    {
        return $this->assignment ??= Assignment::query()->with('session')->find($this->route('assignment'));
    }
}
