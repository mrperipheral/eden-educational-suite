<?php

namespace App\Http\Requests\Student;

use App\Enums\StudentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

/**
 * Change a student's lifecycle status (active / inactive / withdrawn /
 * graduated). Kept separate from the demographic edit form so a "mark
 * withdrawn" is one focused action and the record's lifecycle is auditable in
 * one place later.
 */
class UpdateStudentStatusRequest extends StudentModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(StudentStatus::class)],
        ];
    }

    public function status(): StudentStatus
    {
        return StudentStatus::from($this->validated('status'));
    }
}
