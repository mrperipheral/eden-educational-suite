<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Link a student record to — or unlink it from — an existing application
 * account. M17 does **not** create accounts, send invitations or set
 * passwords; this only points an already-existing member at the student
 * record so they can sign in to the Student Portal.
 *
 * Tenant-safe: the user must be a member of the active school
 * (`school_user`), and no other student record in this school may already be
 * linked to that account.
 */
class LinkStudentUserRequest extends StudentModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();
        $studentId = $this->route('student');

        return [
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('school_user', 'user_id')->where('school_id', $schoolId),
                Rule::unique('students', 'user_id')
                    ->where('school_id', $schoolId)
                    ->ignore($studentId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('user_id') === '' || $this->input('user_id') === null) {
            $this->merge(['user_id' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => __('Select a member of this school.'),
            'user_id.unique' => __('That account is already linked to another student.'),
        ];
    }

    public function userId(): ?int
    {
        $value = $this->validated('user_id');

        return $value === null ? null : (int) $value;
    }
}
