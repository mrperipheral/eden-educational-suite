<?php

namespace App\Http\Requests\Academic;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit an academic level / class. Name, code and order are each unique
 * per school. No level names are assumed.
 */
class LevelRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $levelId = $this->route('level');
        $schoolId = $this->schoolId();

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_levels', 'name')->where('school_id', $schoolId)->ignore($levelId),
            ],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/',
                Rule::unique('academic_levels', 'code')->where('school_id', $schoolId)->ignore($levelId),
            ],
            'position' => [
                'required', 'integer', 'min:1', 'max:999',
                Rule::unique('academic_levels', 'position')->where('school_id', $schoolId)->ignore($levelId),
            ],
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
            'name.unique' => __('This school already has a level with that name.'),
            'code.unique' => __('This school already has a level with that code.'),
            'code.regex' => __('Use letters, numbers, spaces and hyphens only.'),
            'position.unique' => __('Another level already uses that order number.'),
        ];
    }
}
