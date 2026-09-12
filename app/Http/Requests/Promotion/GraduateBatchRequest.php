<?php

namespace App\Http\Requests\Promotion;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Graduate one or more students. `graduation.manage`. `academic_session_id`
 * is the session the school considers the student to have graduated in —
 * validated to belong to the active school; `student_ids` is re-validated
 * authoritatively (active, not already graduated) by
 * `App\Services\Promotion\GraduationService`.
 */
class GraduateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::GraduationManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return array_map('intval', $this->input('student_ids', []));
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? $this->string('notes')->trim()->value() : null;
    }
}
