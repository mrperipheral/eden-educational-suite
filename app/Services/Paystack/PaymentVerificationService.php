<?php

namespace App\Services\Paystack;

use App\Enums\PaymentMethod;
use App\Models\PaystackTransaction;
use App\Models\School;
use App\Services\Fees\FeePaymentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The single, shared idempotency core for M20: both the browser callback and
 * the webhook call {@see self::verifyAndRecord()} with nothing but a
 * reference string, and both get back a safe, correct result no matter the
 * order or how many times either fires. See `docs/paystack.md`.
 *
 * The transaction's own row (`lockForUpdate`, inside a DB transaction) *is*
 * the idempotency guard: whichever caller acquires the lock first while the
 * row is still `pending` does the real work and moves it to a terminal
 * state; every other caller — a duplicate webhook delivery, the user
 * reloading the callback page, a webhook racing the callback — finds a
 * non-`pending` row and returns it unchanged. A successful verification
 * creates *exactly one* M19 `App\Models\FeePayment`, via
 * `App\Services\Fees\FeePaymentService` (never duplicated here).
 *
 * The school is resolved from the transaction row itself — never from the
 * caller — and `TenantContext` is anchored to it before anything
 * tenant-scoped runs, which is what makes this safe to call from a webhook
 * request that started with no tenant context at all.
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly FeePaymentService $payments,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @throws UnknownPaystackReferenceException
     * @throws PaystackApiException the call to Paystack itself failed — the
     *                              transaction is left `pending` for a retry, never guessed at
     */
    public function verifyAndRecord(string $reference): PaystackTransaction
    {
        $owner = PaystackTransaction::withoutGlobalScopes()->where('reference', $reference)->first();

        if ($owner === null) {
            throw new UnknownPaystackReferenceException($reference);
        }

        $this->tenant->setId($owner->school_id);

        return DB::transaction(function () use ($owner) {
            $transaction = PaystackTransaction::withoutGlobalScopes()->whereKey($owner->getKey())->lockForUpdate()->first();

            if ($transaction->isResolved()) {
                return $transaction;
            }

            $school = School::withoutGlobalScopes()->findOrFail($transaction->school_id);
            $secretKey = $school->settings?->paystack_secret_key;

            if (blank($secretKey)) {
                $transaction->markVerificationFailed('Paystack is no longer configured for this school.');

                return $transaction;
            }

            $data = (new PaystackClient($secretKey))->verifyTransaction($transaction->reference);

            return $this->applyVerification($transaction, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data  Paystack's verify-transaction response
     */
    private function applyVerification(PaystackTransaction $transaction, array $data): PaystackTransaction
    {
        $status = $data['status'] ?? null;

        $providerAttributes = [
            'provider_transaction_id' => isset($data['id']) ? (string) $data['id'] : null,
            'gateway_response' => $data['gateway_response'] ?? null,
            'channel' => $data['channel'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ];

        if ($status === 'abandoned') {
            $transaction->markAbandoned($data['gateway_response'] ?? null);

            return $transaction;
        }

        if ($status === 'failed') {
            $transaction->markFailed($data['gateway_response'] ?? 'Payment failed.', $providerAttributes);

            return $transaction;
        }

        if ($status !== 'success') {
            // Anything else (still processing, an unrecognized value, …) is
            // genuinely ambiguous — leave it `pending` for a later retry
            // rather than guessing (M20 spec §7).
            return $transaction;
        }

        $expectedKobo = bcmul((string) $transaction->amount, '100', 0);
        $actualKobo = isset($data['amount']) ? (string) $data['amount'] : null;

        if ($actualKobo === null || bccomp($actualKobo, $expectedKobo, 0) !== 0) {
            $transaction->markVerificationFailed('The verified amount did not match the expected amount.', $providerAttributes);

            return $transaction;
        }

        if (! isset($data['currency']) || strtoupper((string) $data['currency']) !== strtoupper($transaction->currency)) {
            $transaction->markVerificationFailed('The verified currency did not match the expected currency.', $providerAttributes);

            return $transaction;
        }

        $metadataStudentId = $data['metadata']['student_id'] ?? null;

        if ($metadataStudentId !== null && (int) $metadataStudentId !== $transaction->student_id) {
            $transaction->markVerificationFailed('The verified transaction context did not match.', $providerAttributes);

            return $transaction;
        }

        $student = $transaction->student;
        $initiator = $transaction->initiatedBy;
        $allocations = $this->payments->planFifoAllocation($student, (string) $transaction->amount);

        $payment = $this->payments->record($student, [
            'amount' => (string) $transaction->amount,
            'payment_date' => now()->toDateString(),
            'reference' => $transaction->reference,
            'method' => PaymentMethod::Paystack->value,
            'payer_name' => $initiator->name,
            'payer_phone' => null,
            'payer_email' => $initiator->email,
            'notes' => 'Paid online via Paystack.',
        ], $allocations, $initiator);

        $transaction->markSuccessful($payment, $providerAttributes);

        return $transaction;
    }
}
