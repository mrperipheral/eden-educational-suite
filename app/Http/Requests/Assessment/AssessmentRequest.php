<?php

namespace App\Http\Requests\Assessment;

use App\Http\Requests\Assessment\Concerns\ValidatesAcademicContext;
use App\Models\Assignment;
use App\Models\Student;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Create an assessment. The academic context (session + period + level + arm +
 * subject) is validated here and then **fixed** — {@see UpdateAssessmentRequest}
 * only touches the title / category / max score / instructions.
 *
 * `authorize()` additionally checks the acting user may record for the chosen
 * class + subject ({@see AssessmentAuthorizer}) — a teacher only for a
 * `(level, subject)` they hold an active assignment for.
 */
class AssessmentRequest extends AssessmentModuleRequest
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
        $schoolId = $this->schoolId();

        return [
            ...$this->academicContextRules($schoolId),
            'assessment_category_id' => [
                'required', 'integer',
                Rule::exists('assessment_categories', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'assignment_id' => [
                'nullable', 'integer',
                Rule::exists('assignments', 'id')->where('school_id', $schoolId),
            ],
            'title' => ['required', 'string', 'max:150'],
            'assessment_date' => ['required', 'date', 'after:2000-01-01'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,2'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAcademicContext($validator, 'assessment_date');

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The optional assignment must cover the same class + subject.
            if ($this->filled('assignment_id')) {
                $assignment = Assignment::query()->find($this->input('assignment_id'));
                if ($assignment && (
                    $assignment->academic_level_id !== (int) $this->input('academic_level_id')
                    || $assignment->level_arm_id !== (int) $this->input('level_arm_id')
                    || $assignment->subject_id !== (int) $this->input('subject_id')
                )) {
                    $validator->errors()->add('assignment_id', __('That assignment is for a different class or subject.'));
                }
            }

            if ($validator->errors()->isEmpty() && ! $this->classHasEligibleStudents()) {
                $validator->errors()->add('level_arm_id', __('No students are enrolled in that class on that date.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('assignment_id') === '') {
            $this->merge(['assignment_id' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->academicContextMessages(),
            'assessment_category_id.exists' => __('The selected category is invalid.'),
            'assignment_id.exists' => __('The selected assignment is invalid.'),
            'max_score.decimal' => __('The maximum score may have at most two decimal places.'),
            'max_score.gt' => __('The maximum score must be greater than zero.'),
        ];
    }

    private function classHasEligibleStudents(): bool
    {
        $date = (string) $this->input('assessment_date');

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
