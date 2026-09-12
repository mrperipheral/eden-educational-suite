<?php

namespace Tests\Feature\Paystack;

use App\Enums\PaystackTransactionStatus;
use App\Models\FeePayment;
use App\Models\PaystackTransaction;
use App\Models\User;
use App\Services\Paystack\PaymentVerificationService;
use Illuminate\Support\Str;

class WebhookTest extends PaystackTestCase
{
    public function test_a_validly_signed_successful_event_records_the_payment(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, secret: 'sk_test_webhook_secret');
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);
        $signature = $this->signatureFor($body, 'sk_test_webhook_secret');

        $response = $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        $this->assertSame(PaystackTransactionStatus::Successful, $transaction->fresh()->status);
        $this->assertSame(1, FeePayment::count());
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_is_processed(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, secret: 'sk_test_webhook_secret');
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);

        $response = $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => 'not-a-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
        $this->assertTrue($transaction->fresh()->isPending());
        $this->assertSame(0, FeePayment::count());
    }

    public function test_a_missing_signature_header_is_rejected(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, secret: 'sk_test_webhook_secret');
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);

        $response = $this->call('POST', route('webhooks.paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $response->assertStatus(401);
    }

    public function test_a_duplicate_webhook_delivery_never_creates_a_second_payment(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, secret: 'sk_test_webhook_secret');
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);
        $signature = $this->signatureFor($body, 'sk_test_webhook_secret');
        $headers = ['HTTP_x-paystack-signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('webhooks.paystack'), [], [], [], $headers, $body)->assertOk();
        $this->call('POST', route('webhooks.paystack'), [], [], [], $headers, $body)->assertOk();

        $this->assertSame(1, FeePayment::count());
    }

    public function test_a_webhook_arriving_after_the_browser_already_verified_is_a_no_op(): void
    {
        $school = $this->newSchool();
        $this->configurePaystack($school, secret: 'sk_test_webhook_secret');
        $scaffold = $this->scaffold($school);
        $student = $this->enrolledStudent($school, $scaffold);
        $this->chargeFor($school, $student, ['amount' => '5000.00']);
        $transaction = $this->pendingTransaction($school, $student, '5000.00');

        $this->fakeVerify(['reference' => $transaction->reference, 'status' => 'success', 'amount' => 500000]);

        // The "browser callback" path.
        app(PaymentVerificationService::class)->verifyAndRecord($transaction->reference);
        $this->assertSame(1, FeePayment::count());

        // The webhook for the same charge, arriving afterwards.
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transaction->reference]]);
        $signature = $this->signatureFor($body, 'sk_test_webhook_secret');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame(1, FeePayment::count(), 'the webhook must not allocate the same payment twice');
    }

    public function test_an_unknown_reference_is_acknowledged_but_ignored(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'PSK-NOBODY-KNOWS-THIS']]);

        $response = $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => 'irrelevant', 'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();
        $this->assertSame(0, FeePayment::withoutGlobalScopes()->count());
    }

    public function test_a_payload_with_no_reference_is_acknowledged_and_ignored(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => []]);

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => 'irrelevant', 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    public function test_school_bs_secret_cannot_be_used_to_forge_a_webhook_for_school_as_transaction(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $this->configurePaystack($schoolA, secret: 'sk_test_school_a_secret');
        $this->configurePaystack($schoolB, secret: 'sk_test_school_b_secret');
        $scaffoldA = $this->scaffold($schoolA);
        $studentA = $this->enrolledStudent($schoolA, $scaffoldA);
        $this->chargeFor($schoolA, $studentA, ['amount' => '5000.00']);
        $transactionA = $this->pendingTransaction($schoolA, $studentA, '5000.00');

        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $transactionA->reference]]);
        // Signed with school B's secret — wrong key for this transaction's owner.
        $signature = $this->signatureFor($body, 'sk_test_school_b_secret');

        $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_x-paystack-signature' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(401);

        $this->assertTrue($transactionA->fresh()->isPending());
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
