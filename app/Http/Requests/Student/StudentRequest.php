<?php

namespace App\Http\Requests\Student;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create or edit a student's demographic / contact record. `status` is **not**
 * here — it changes only through the dedicated status endpoint
 * ({@see UpdateStudentStatusRequest}).
 *
 * `admission_number` is unique per school; a cross-school route id never reaches
 * this request (the controller 404s first), so the uniqueness scope is a plain
 * `where('school_id', <tenant>)`.
 */
class StudentRequest extends StudentModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $studentId = $this->route('student');

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'preferred_name' => ['nullable', 'string', 'max:60'],

            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['nullable', new Enum(Gender::class)],

            'admission_number' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/\- ]*$/',
                Rule::unique('students', 'admission_number')
                    ->where('school_id', $this->schoolId())
                    ->ignore($studentId),
            ],
            'admitted_on' => ['nullable', 'date', 'after:1950-01-01'],

            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('admission_number')) {
            $this->merge(['admission_number' => trim((string) $this->input('admission_number'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admission_number.unique' => __('This school already has a student with that admission number.'),
            'admission_number.regex' => __('Use letters, numbers, spaces, slashes and hyphens only.'),
        ];
    }
}
