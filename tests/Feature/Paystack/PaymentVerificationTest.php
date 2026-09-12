<?php

namespace Tests\Feature\Paystack;

use App\Enums\PaystackTransactionStatus;
use App\Models\FeePayment;
use App\Models\PaystackTransaction;
use App\Models\User;
use App\Services\Paystack\PaymentVerificationService;
use App\Services\Paystack\PaystackApiException;
use App\Services\Paystack\UnknownPaystackReferenceException;
use Illuminate\Support\Str;

/**
 * Exercises `App\Services\Paystack\PaymentVerificationService` directly —
 * the shared core both the browser callback and the webhook call.
 */
class PaymentVerificationTest extends PaystackTestCase
{
    public function test_a_successful_verification_creates_exactly_one_m19_payment(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'amount' => 500000, 'currency' => 'NGN', 'status' => 'success']);

        $result = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame(PaystackTransactionStatus::Successful, $result->status);
        $this->assertSame(1, FeePayment::count());
        $this->assertNotNull($result->fee_payment_id);

        $this->enterSchool($school);
        $this->assertSame('0.00', $charge->fresh()->outstandingBalance());
        $this->app->forgetScopedInstances();
    }

    public function test_a_failed_transaction_is_marked_failed_and_creates_no_payment(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'failed', 'gateway_response' => 'Declined']);

        $result = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame(PaystackTransactionStatus::Failed, $result->status);
        $this->assertSame(0, FeePayment::count());
    }

    public function test_an_abandoned_transaction_is_marked_abandoned(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'abandoned']);

        $result = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame(PaystackTransactionStatus::Abandoned, $result->status);
        $this->assertSame(0, FeePayment::count());
    }

    public function test_amount_mismatch_is_flagged_as_verification_failed_not_accepted(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        // Paystack reports a successful charge of a *different* amount.
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 100000]);

        $result = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame(PaystackTransactionStatus::VerificationFailed, $result->status);
        $this->assertSame(0, FeePayment::count());
    }

    public function test_currency_mismatch_is_flagged_as_verification_failed(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000, 'currency' => 'GHS']);

        $result = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame(PaystackTransactionStatus::VerificationFailed, $result->status);
        $this->assertSame(0, FeePayment::count());
    }

    public function test_an_unknown_reference_throws_and_touches_nothing(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);

        $this->expectException(UnknownPaystackReferenceException::class);
        app(PaymentVerificationService::class)->verifyAndRecord('PSK-DOES-NOT-EXIST');
    }

    public function test_an_already_processed_transaction_is_returned_unchanged_not_reprocessed(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);
        $first = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);
        $this->assertSame(1, FeePayment::count());

        // Even if Paystack (or our own fake) were now to report something
        // different, a second call must never touch it again.
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'failed']);
        $second = app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->assertSame($first->fee_payment_id, $second->fee_payment_id);
        $this->assertSame(PaystackTransactionStatus::Successful, $second->status);
        $this->assertSame(1, FeePayment::count(), 'repeated verification must never create a second payment');
    }

    public function test_school_a_cannot_verify_a_school_b_transaction_by_guessing_its_reference(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->configurePaystack($schoolA);
        $this->configurePaystack($schoolB);
        $scaffoldB = $this->scaffold($schoolB);
        $studentB = $this->enrolledStudent($schoolB, $scaffoldB);
        $this->chargeFor($schoolB, $studentB, ['amount' => '5000.00']);
        $transactionB = $this->pendingTransaction($schoolB, $studentB, '5000.00');

        $this->fakeVerify(['reference' => $transactionB->reference, 'status' => 'success', 'amount' => 500000]);

        // Processing determines the school from the transaction's own stored
        // row, never from whichever tenant happens to be active when called.
        $this->enterSchool($schoolA);
        $result = app(PaymentVerificationService::class)->verifyAndRecord($transactionB->reference);
        $this->app->forgetScopedInstances();

        $this->assertSame($schoolB->id, $result->school_id);
        $this->enterSchool($schoolB);
        $this->assertSame(1, FeePayment::withoutGlobalScopes()->where('school_id', $schoolB->id)->count());
        $this->assertSame(0, FeePayment::withoutGlobalScopes()->where('school_id', $schoolA->id)->count());
        $this->app->forgetScopedInstances();
    }

    public function test_an_unreachable_paystack_api_leaves_the_transaction_pending_for_retry(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerifyUnreachable();

        $this->expectException(PaystackApiException::class);

        try {
            app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);
        } finally {
            $this->assertTrue($transaction->fresh()->isPending(), 'an ambiguous API failure must never change the transaction status');
            $this->assertSame(0, FeePayment::count());
        }
    }

    private function pendingTransaction($school, $student, string $amount): PaystackTransaction
    {
        $this->enterSchool($school);
        $user = User::factory()->create();
        $transaction = new PaystackTransaction([
            'student_id' => $student->id,
            'reference' => 'PSK-'.Str::upper(Str::random(20)),
            'amount' => $amount,
            'currency' => 'NGN',
        ]);
        $transaction->initiated_by = $user->id;
        $transaction->save();
        $this->app->forgetScopedInstances();

        return $transaction;
    }
}
