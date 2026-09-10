<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Add an existing user to the current school with a role.
 *
 * Coarse gate: `member.assign-role` in the active school. The specific role is
 * additionally checked against the actor's tier in the controller
 * (`User::canGrantRole()`), so no privilege escalation is possible.
 */
class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::MemberAssignRole) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::exists('users', 'email')->where('status', UserStatus::Active->value),
                function (string $attribute, mixed $value, \Closure $fail) use ($schoolId): void {
                    $user = User::query()->where('email', $value)->first();

                    if ($user !== null && $user->belongsToSchool($schoolId)) {
                        $fail(__('That person is already a member of this school.'));
                    }
                },
            ],
            'role' => ['required', new Enum(Role::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => __('No active account was found for that email address.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function role(): Role
    {
        return Role::from($this->validated()['role']);
    }

    public function targetUser(): User
    {
        return User::query()->where('email', $this->validated()['email'])->firstOrFail();
    }
}
