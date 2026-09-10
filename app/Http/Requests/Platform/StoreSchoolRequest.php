<?php

namespace App\Http\Requests\Platform;

use App\Enums\UserStatus;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Provision a new school. Platform-admin only (SchoolPolicy::create).
 */
class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', School::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'nullable', 'string', 'lowercase', 'min:2', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('schools', 'slug'),
            ],
            // Optional: seat an existing, active account as the first School Admin.
            'initial_admin_email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::exists('users', 'email')->where('status', UserStatus::Active->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('The slug may only contain lowercase letters, numbers and single hyphens.'),
            'slug.unique' => __('That slug is already taken by another school.'),
            'initial_admin_email.exists' => __('No active account was found for that email address.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => mb_strtolower(trim((string) $this->input('slug')))]);
        }

        if ($this->filled('initial_admin_email')) {
            $this->merge(['initial_admin_email' => mb_strtolower(trim((string) $this->input('initial_admin_email')))]);
        }
    }

    public function initialAdmin(): ?User
    {
        $email = $this->validated()['initial_admin_email'] ?? null;

        return $email ? User::query()->where('email', $email)->first() : null;
    }
}
