<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AssignMemberRoleRequest extends FormRequest
{
    /**
     * Coarse gate: must be able to assign roles at all in the active school.
     * The fine-grained "not yourself / no escalation" checks are the
     * MembershipPolicy, applied in the controller once the membership is loaded.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::MemberAssignRole) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', new Enum(Role::class)],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->validated()['role']);
    }
}
