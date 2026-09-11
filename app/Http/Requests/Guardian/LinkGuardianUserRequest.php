<?php

namespace App\Http\Requests\Guardian;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Link a guardian record to — or unlink it from — an existing application
 * account. M16 does **not** create accounts, send invitations or set
 * passwords; this only points an already-existing member at the guardian
 * record so they can sign in to the Parent Portal.
 *
 * Tenant-safe: the user must be a member of the active school
 * (`school_user`), and no other guardian record in this school may already be
 * linked to that account.
 */
class LinkGuardianUserRequest extends GuardianModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();
        $guardianId = $this->route('guardian');

        return [
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('school_user', 'user_id')->where('school_id', $schoolId),
                Rule::unique('guardians', 'user_id')
                    ->where('school_id', $schoolId)
                    ->ignore($guardianId),
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
            'user_id.unique' => __('That account is already linked to another guardian.'),
        ];
    }

    public function userId(): ?int
    {
        $value = $this->validated('user_id');

        return $value === null ? null : (int) $value;
    }
}
