<?php

namespace App\Http\Requests\Guardian;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Create or edit a guardian's contact record. No identity, financial, medical or
 * emergency fields — contact details only (see `docs/guardian-management.md`).
 */
class GuardianRequest extends GuardianModuleRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'preferred_name' => ['nullable', 'string', 'max:60'],

            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/'],
            'alt_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/'],

            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Use digits, spaces, and + ( ) - only.'),
            'alt_phone.regex' => __('Use digits, spaces, and + ( ) - only.'),
        ];
    }
}
