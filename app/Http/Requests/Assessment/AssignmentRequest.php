<?php

namespace App\Http\Requests\Assessment;

use App\Http\Requests\Assessment\Concerns\ValidatesAcademicContext;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Create an assignment. The academic context is validated and then fixed;
 * {@see UpdateAssignmentRequest} handles title / instructions / dates / max
 * score on a draft.
 *
 * `authorize()` checks the acting user may record for the chosen class +
 * subject ({@see AssessmentAuthorizer}).
 */
class AssignmentRequest extends AssessmentModuleRequest
{
    use ValidatesAcademicContext;

    public function authorize(): bool
    {
        return parent::authorize()
            && app(AssessmentAuthorizer::class)->canRecordFor(
                $this->user(),
                (int) $this->input('academic_level_id'),
                (int) $this->input('level_arm_id'),
                (int) $this->input('subject_id'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->academicContextRules($this->schoolId()),
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
            $this->validateAcademicContext($validator, 'assigned_on');

            if (! $validator->errors()->has('due_on') && ! $validator->errors()->has('academic_session_id')) {
                $this->validateDueDateInSession($validator);
            }

            if ($validator->errors()->isEmpty() && ! $this->classHasEligibleStudents()) {
                $validator->errors()->add('level_arm_id', __('No students are enrolled in that class on that date.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
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
            ...$this->academicContextMessages(),
            'due_on.after_or_equal' => __('The due date cannot be before the assigned date.'),
            'max_score.decimal' => __('The maximum score may have at most two decimal places.'),
        ];
    }

    private function validateDueDateInSession(Validator $validator): void
    {
        $session = AcademicSession::query()->find($this->input('academic_session_id'));
        $due = (string) $this->input('due_on');

        if ($session && $due > $session->ends_on->toDateString()) {
            $validator->errors()->add('due_on', __('The due date is outside the selected session.'));
        }
    }

    private function classHasEligibleStudents(): bool
    {
        $date = (string) $this->input('assigned_on');

        return Student::query()
            ->whereHas('enrollments', fn (Builder $q) => $q
                ->where('academic_session_id', $this->input('academic_session_id'))
                ->where('academic_level_id', $this->input('academic_level_id'))
                ->where('level_arm_id', $this->input('level_arm_id'))
                ->where('started_on', '<=', $date)
                ->where(fn (Builder $q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $date)))
            ->exists();
    }
}
