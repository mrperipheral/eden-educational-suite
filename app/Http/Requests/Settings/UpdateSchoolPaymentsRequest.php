<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Per-school Paystack configuration (M20, `docs/paystack.md`). Note that
 * `paystack_secret_key` here is only ever the **freshly submitted** value —
 * the controller decides whether to actually overwrite the stored one (a
 * blank field means "leave the existing key alone"), since the secret is
 * never rendered back into the form for editing.
 */
class UpdateSchoolPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::SchoolSettingsUpdate) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paystack_enabled' => ['sometimes', 'boolean'],
            'paystack_public_key' => ['nullable', 'string', 'max:100'],
            'paystack_secret_key' => ['nullable', 'string', 'max:255'],
            'paystack_test_mode' => ['sometimes', 'boolean'],
        ];
    }

    public function publicKey(): ?string
    {
        return $this->filled('paystack_public_key') ? $this->string('paystack_public_key')->trim()->value() : null;
    }

    /** The freshly submitted secret, or `null` if the field was left blank ("keep the existing one"). */
    public function newSecretKey(): ?string
    {
        return $this->filled('paystack_secret_key') ? $this->string('paystack_secret_key')->trim()->value() : null;
    }
}
