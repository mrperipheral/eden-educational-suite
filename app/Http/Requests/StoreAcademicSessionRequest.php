<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::SchoolSettingsUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:60',
                // Uniqueness is per school; the school id comes from the tenant
                // context, never from request input.
                Rule::unique('academic_sessions', 'name')
                    ->where('school_id', app(TenantContext::class)->idOrFail()),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => __('This school already has an academic session with that name.'),
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }
}
