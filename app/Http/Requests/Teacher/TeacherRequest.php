<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit a teacher's professional record. `status` and `user_id` are
 * **not** here — they change only through their dedicated endpoints
 * ({@see UpdateTeacherStatusRequest}, {@see LinkTeacherUserRequest}).
 *
 * `employee_number` is unique per school; a cross-school route id never reaches
 * this request (the controller 404s first), so the uniqueness scope is a plain
 * `where('school_id', <tenant>)`.
 */
class TeacherRequest extends TeacherModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $teacherId = $this->route('teacher');

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'preferred_name' => ['nullable', 'string', 'max:60'],

            'employee_number' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/\- ]*$/',
                Rule::unique('teachers', 'employee_number')
                    ->where('school_id', $this->schoolId())
                    ->ignore($teacherId),
            ],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/'],
            'employed_on' => ['nullable', 'date', 'after:1950-01-01', 'before:2100-01-01'],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('employee_number')) {
            $this->merge(['employee_number' => trim((string) $this->input('employee_number'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.unique' => __('This school already has a teacher with that employee number.'),
            'employee_number.regex' => __('Use letters, numbers, spaces, slashes and hyphens only.'),
            'phone.regex' => __('Use digits, spaces, and + ( ) - only.'),
        ];
    }
}
