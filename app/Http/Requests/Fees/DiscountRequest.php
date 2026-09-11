<?php

namespace App\Http\Requests\Fees;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Apply a discount to a charge. `fees.adjust` — a distinct, narrower
 * permission than `fees.manage`, since discounting/waiving is a financial
 * correction, not routine configuration.
 */
class DiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::FeesAdjust) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function amount(): string
    {
        return (string) $this->input('amount');
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->value() : null;
    }
}
