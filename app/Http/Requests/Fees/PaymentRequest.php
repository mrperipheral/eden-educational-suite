<?php

namespace App\Http\Requests\Fees;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Record a manual payment, optionally allocating it across one or more of
 * the student's outstanding charges. `fees.record-payment`. `reference` is
 * unique **within the school** — the duplicate-reference guard the spec
 * requires; `allocations` is a `charge_id => amount` map from the form,
 * re-validated authoritatively (over-allocation, cross-student) by
 * `App\Services\Fees\FeePaymentService` inside a DB transaction — this
 * request only catches the obvious client-side mistakes early.
 *
 * `method` excludes {@see PaymentMethod::Paystack} — that value is written
 * only by `App\Services\Paystack\PaymentVerificationService` once a
 * transaction is independently verified (M20, `docs/paystack.md`); nobody
 * manually recording a payment here may claim it was paid online.
 */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::FeesRecordPayment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => [
                'required', 'string', 'max:60',
                Rule::unique('fee_payments', 'reference')->where('school_id', $schoolId),
            ],
            'method' => ['required', new Enum(PaymentMethod::class), Rule::notIn([PaymentMethod::Paystack->value])],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'payer_email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.unique' => __('A payment with that reference already exists.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $total = array_reduce(
                array_filter((array) $this->input('allocations', [])),
                fn (string $carry, $amount) => bcadd($carry, (string) $amount, 2),
                '0.00',
            );

            if ($this->filled('amount') && bccomp($total, (string) $this->input('amount'), 2) === 1) {
                $validator->errors()->add('allocations', __('The total allocated cannot exceed the payment amount.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'amount' => $this->input('amount'),
            'payment_date' => $this->input('payment_date'),
            'reference' => $this->string('reference')->trim()->value(),
            'method' => $this->input('method'),
            'payer_name' => $this->filled('payer_name') ? $this->string('payer_name')->trim()->value() : null,
            'payer_phone' => $this->filled('payer_phone') ? $this->string('payer_phone')->trim()->value() : null,
            'payer_email' => $this->filled('payer_email') ? $this->string('payer_email')->trim()->value() : null,
            'notes' => $this->filled('notes') ? $this->string('notes')->trim()->value() : null,
        ];
    }

    /**
     * @return array<int, string> charge id => amount, zero/empty entries dropped
     */
    public function allocations(): array
    {
        $allocations = [];

        foreach ((array) $this->input('allocations', []) as $chargeId => $amount) {
            if ($amount !== null && $amount !== '' && bccomp((string) $amount, '0.00', 2) === 1) {
                $allocations[(int) $chargeId] = (string) $amount;
            }
        }

        return $allocations;
    }
}
