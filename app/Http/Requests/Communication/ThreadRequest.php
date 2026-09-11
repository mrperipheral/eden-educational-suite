<?php

namespace App\Http\Requests\Communication;

use App\Enums\CommunicationCategory;
use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Open a new Communication Hub thread, with its first message. `student_id` /
 * `guardian_id` / `assigned_to` are validated to belong to the active school
 * — never trusted as-is — so a cross-school id 422s instead of silently
 * linking another tenant's record.
 */
class ThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CommunicationCreate) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', new Enum(CommunicationCategory::class)],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'guardian_id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->where('school_id', $schoolId)],
            'assigned_to' => [
                'nullable', 'integer',
                Rule::exists('school_user', 'user_id')->where('school_id', $schoolId),
            ],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'subject' => $this->string('subject')->trim()->value(),
            'category' => $this->input('category'),
            'student_id' => $this->input('student_id') ?: null,
            'guardian_id' => $this->input('guardian_id') ?: null,
            'assigned_to' => $this->input('assigned_to') ?: $this->user()->getKey(),
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }
}
