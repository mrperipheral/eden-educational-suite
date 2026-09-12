<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Initiate an online payment (M20, `docs/paystack.md`) — used by both the
 * Parent Portal and the Student Portal, each of which has already gated the
 * route on its own `portal.parent` / `portal.student` permission; this
 * request re-checks whichever the signed-in user actually holds as a second,
 * defense-in-depth layer, matching every other write action in the app.
 *
 * Only `amount` is taken from the request. Everything that actually matters
 * — which student, whether Paystack is configured, whether the amount is
 * within the outstanding balance — is re-validated server-side by
 * `App\Services\Paystack\PaymentInitiationService`, never trusted from here.
 */
class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('portal.parent') || $user->can('portal.student'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
        ];
    }

    public function amount(): string
    {
        return (string) $this->input('amount');
    }
}
