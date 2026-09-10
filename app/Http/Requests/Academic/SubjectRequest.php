<?php

namespace App\Http\Requests\Academic;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit a subject. Name and code are unique per school. No subject
 * list is assumed.
 */
class SubjectRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $subjectId = $this->route('subject');
        $schoolId = $this->schoolId();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'name')->where('school_id', $schoolId)->ignore($subjectId),
            ],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/',
                Rule::unique('subjects', 'code')->where('school_id', $schoolId)->ignore($subjectId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseCode();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This school already has a subject with that name.'),
            'code.unique' => __('This school already has a subject with that code.'),
            'code.regex' => __('Use letters, numbers, spaces and hyphens only.'),
        ];
    }
}
