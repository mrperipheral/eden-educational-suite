<?php

namespace Tests\Feature\Paystack;

use App\Models\School;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Fees\FeesTestCase;

/**
 * Shared setup for the Online Fee Payment / Paystack feature tests (M20,
 * `docs/paystack.md`). Extends the M19 {@see FeesTestCase} to reuse its
 * scaffold/student/charge helpers rather than duplicating them — a payment
 * still needs the exact same fee-structure context M19 tests use.
 *
 * Paystack itself is never actually called: every test fakes the HTTP layer
 * (`Http::fake()`), so `App\Services\Paystack\PaystackClient` runs for real
 * against a fake transport — exercising the real integration code without
 * needing live credentials.
 */
abstract class PaystackTestCase extends FeesTestCase
{
    protected function configurePaystack(School $school, bool $enabled = true, string $secret = 'sk_test_fake_secret'): void
    {
        $this->enterSchool($school);
        $settings = $school->settings()->firstOrCreate([]);
        $settings->fill([
            'paystack_enabled' => $enabled,
            'paystack_public_key' => 'pk_test_fake',
            'paystack_secret_key' => $secret,
            'paystack_test_mode' => true,
        ])->save();
        $this->app->forgetScopedInstances();
    }

    protected function fakeInitialize(?string $authorizationUrl = null): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => $authorizationUrl ?? 'https://checkout.paystack.com/fake-checkout',
                    'access_code' => 'access-code-123',
                    'reference' => 'ignored-by-us',
                ],
            ], 200),
        ]);
    }

    protected function fakeInitializeFails(): void
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => false, 'message' => 'Invalid key',
            ], 401),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeVerify(array $overrides = []): void
    {
        $data = array_merge([
            'id' => 999999,
            'status' => 'success',
            'reference' => $overrides['reference'] ?? 'unset',
            'amount' => 500000,
            'currency' => 'NGN',
            'gateway_response' => 'Successful',
            'channel' => 'card',
            'paid_at' => now()->toIso8601String(),
            'metadata' => [],
        ], $overrides);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true, 'message' => 'Verification successful', 'data' => $data,
            ], 200),
        ]);
    }

    protected function fakeVerifyUnreachable(): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([], 500),
        ]);
    }

    protected function signatureFor(string $rawBody, string $secret): string
    {
        return hash_hmac('sha512', $rawBody, $secret);
    }
}
