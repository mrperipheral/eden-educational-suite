<?php

namespace App\Http\Requests\Fees;

use App\Enums\Permission;
use App\Models\FeeStructure;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Raise a charge against a student — either from an active
 * {@see FeeStructure} or entered manually. `fees.manage`. The
 * student's current enrollment (level/arm), never request input, decides the
 * charge's class context — see `App\Services\Fees\FeeChargeService`.
 */
class ChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::FeesManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'fee_structure_id' => [
                'nullable', 'integer',
                Rule::exists('fee_structures', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'fee_category_id' => [
                'required_without:fee_structure_id', 'nullable', 'integer',
                Rule::exists('fee_categories', 'id')->where('school_id', $schoolId),
            ],
            'academic_session_id' => [
                'required_without:fee_structure_id', 'nullable', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'academic_period_id' => [
                'nullable', 'integer',
                Rule::exists('academic_periods', 'id')->where('school_id', $schoolId),
            ],
            'amount' => ['required_without:fee_structure_id', 'nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function usesStructure(): bool
    {
        return $this->filled('fee_structure_id');
    }

    /**
     * @return array{fee_category_id:int, academic_session_id:int, academic_period_id:?int, description:string, amount:string}
     */
    public function manualPayload(): array
    {
        return [
            'fee_category_id' => $this->integer('fee_category_id'),
            'academic_session_id' => $this->integer('academic_session_id'),
            'academic_period_id' => $this->input('academic_period_id') ?: null,
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : __('Fee charge'),
            'amount' => $this->input('amount'),
        ];
    }

    public function description(): ?string
    {
        return $this->filled('description') ? $this->string('description')->trim()->value() : null;
    }
}
