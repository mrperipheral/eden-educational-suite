<?php

namespace App\Http\Requests\Communication;

use App\Enums\CommunicationCategory;
use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Edit a thread's routing — subject, category and assignee.
 * `communication.manage` (Principal / School Admin) only; the lifecycle
 * status itself changes only through the dedicated resolve/escalate/reopen
 * endpoints, never here.
 */
class UpdateThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CommunicationManage) ?? false;
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
            'assigned_to' => [
                'nullable', 'integer',
                Rule::exists('school_user', 'user_id')->where('school_id', $schoolId),
            ],
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
            'assigned_to' => $this->input('assigned_to') ?: null,
        ];
    }
}
