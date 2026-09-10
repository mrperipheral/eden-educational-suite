<?php

namespace App\Http\Requests\Academic;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit an academic session (year). `name` is unique per school; the
 * end date must be after the start. `is_current` is applied through
 * `AcademicSession::makeCurrent()`, never mass-assigned.
 */
class SessionRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $sessionId = $this->route('session');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('academic_sessions', 'name')
                    ->where('school_id', $this->schoolId())
                    ->ignore($sessionId),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This school already has an academic session with that name.'),
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }
}
