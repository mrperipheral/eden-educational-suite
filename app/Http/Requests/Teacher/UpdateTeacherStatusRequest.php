<?php

namespace App\Http\Requests\Teacher;

use App\Enums\TeacherStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

/**
 * Change a teacher's employment status (active / inactive / suspended /
 * resigned). Kept separate from the record edit form so a lifecycle change is
 * one focused, auditable action.
 */
class UpdateTeacherStatusRequest extends TeacherModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TeacherStatus::class)],
        ];
    }

    public function status(): TeacherStatus
    {
        return TeacherStatus::from($this->validated('status'));
    }
}
