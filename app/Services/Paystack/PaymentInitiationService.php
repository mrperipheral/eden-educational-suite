<?php

namespace App\Services\Paystack;

use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use App\Services\Fees\FeeStatementBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts an online payment: validates the amount against the student's
 * **currently outstanding** balance (via M19's own
 * {@see FeeStatementBuilder} — never a client-submitted total), creates the
 * local {@see PaystackTransaction} row, then initializes the transaction
 * with Paystack. If the Paystack call fails, the whole thing rolls back —
 * there is never a `pending` row with nothing behind it at Paystack. See
 * `docs/paystack.md`.
 */
class PaymentInitiationService
{
    public function __construct(private readonly FeeStatementBuilder $statements) {}

    /**
     * @throws PaystackNotConfiguredException
     * @throws \InvalidArgumentException invalid or excessive amount
     * @throws PaystackApiException
     */
    public function initiate(Student $student, User $initiatedBy, string $amount, string $callbackUrl): PaystackTransaction
    {
        $school = $student->school;
        $settings = $school->settings;

        if ($settings === null || ! $settings->paystackReady()) {
            throw new PaystackNotConfiguredException;
        }

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new \InvalidArgumentException('The amount must be greater than zero.');
        }

        $outstanding = $this->statements->statementFor($student)['totalOutstanding'];

        if (bccomp($amount, $outstanding, 2) === 1) {
            throw new \InvalidArgumentException('The amount cannot exceed the outstanding balance.');
        }

        $currency = $settings->currency ?: 'NGN';

        return DB::transaction(function () use ($student, $initiatedBy, $amount, $currency, $callbackUrl, $settings) {
            $transaction = new PaystackTransaction([
                'student_id' => $student->getKey(),
                'reference' => $this->generateReference(),
                'amount' => $amount,
                'currency' => $currency,
            ]);
            $transaction->initiated_by = $initiatedBy->getKey();
            $transaction->save();

            $client = new PaystackClient($settings->paystack_secret_key);

            $data = $client->initializeTransaction([
                'email' => $initiatedBy->email,
                'amount' => bcmul($amount, '100', 0),
                'currency' => $currency,
                'reference' => $transaction->reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'student_id' => $student->getKey(),
                    'school_id' => $student->getAttribute('school_id'),
                ],
            ]);

            if (empty($data['authorization_url'])) {
                throw new PaystackApiException('Paystack did not return a checkout URL.');
            }

            $transaction->recordInitialization($data['authorization_url'], $data['access_code'] ?? null);

            return $transaction;
        });
    }

    private function generateReference(): string
    {
        do {
            $reference = 'PSK-'.Str::upper(Str::random(24));
        } while (PaystackTransaction::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }
}
