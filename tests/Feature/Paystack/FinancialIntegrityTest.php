<?php

namespace Tests\Feature\Paystack;

use App\Enums\Role;
use App\Models\FeePayment;
use App\Models\PaystackTransaction;
use App\Models\User;
use App\Services\Paystack\PaymentVerificationService;
use Illuminate\Support\Str;

/**
 * End-to-end financial correctness for M20: a verified online payment must
 * behave exactly like any other M19 payment — correct FIFO allocation,
 * correct balance, visible in the statement and payment history.
 */
class FinancialIntegrityTest extends PaystackTestCase
{
    public function test_an_online_payment_is_allocated_fifo_across_multiple_outstanding_charges(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);

        $this->enterSchool($school);
        $older = $this->chargeFor($school, $student, ['amount' => '3000.00']);
        sleep(0); // ensure distinct created_at ordering isn't required beyond insertion order
        $newer = $this->chargeFor($school, $student, ['amount' => '4000.00']);
        $this->app->forgetScopedInstances();

        $transaction = $this->pendingTransaction($school, $student, '5000.00');
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->enterSchool($school);
        $this->assertSame('0.00', $older->fresh()->outstandingBalance(), 'the older charge must be paid off first');
        $this->assertSame('2000.00', $newer->fresh()->outstandingBalance(), 'the remainder applies to the next charge');
        $this->app->forgetScopedInstances();
    }

    public function test_a_verified_payment_appears_in_the_fee_statement_and_payment_history(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);

        $this->actingAsRole($school, Role::Bursar);
        $response = $this->get(route('fees.students.show', $student));

        $response->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Paystack');
    }

    public function test_a_voided_online_payment_is_excluded_from_balances_like_any_other_payment(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school);
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $charge = $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');
        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);
        $this->enterSchool($school);
        $this->assertSame('0.00', $charge->fresh()->outstandingBalance());
        $payment = FeePayment::first();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($school, Role::Bursar);
        $this->post(route('fees.payments.void', $payment), ['reason' => 'Chargeback'])->assertRedirect();

        $this->enterSchool($school);
        $this->assertSame('5000.00', $charge->fresh()->outstandingBalance());
        $this->app->forgetScopedInstances();
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
