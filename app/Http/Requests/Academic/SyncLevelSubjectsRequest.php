<?php

namespace App\Http\Requests\Academic;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Set the list of subjects an academic level offers. Every id must be a subject
 * belonging to the active school — a cross-school id is rejected here, so the
 * `level_subject` link can never span tenants.
 */
class SyncLevelSubjectsRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'subjects' => ['array'],
            'subjects.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id')->where('school_id', $this->schoolId()),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subjects' => array_values(array_filter((array) $this->input('subjects', []), fn ($v) => $v !== null && $v !== '')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subjects.*.exists' => __('One of the selected subjects does not belong to this school.'),
        ];
    }

    /**
     * @return list<int>
     */
    public function subjectIds(): array
    {
        return array_map('intval', $this->validated('subjects', []));
    }
}
