<?php

namespace App\Http\Requests\Fees;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A bare optional reason — used for waive / unwaive / void, every one of
 * which is `fees.adjust` (a financial correction, never routine
 * configuration).
 */
class ReasonRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->value() : null;
    }
}
