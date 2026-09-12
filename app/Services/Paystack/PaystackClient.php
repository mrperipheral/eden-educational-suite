<?php

namespace App\Services\Paystack;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A thin wrapper over Paystack's Transaction API — the only place in the
 * application that talks to Paystack over HTTP. Deliberately minimal (two
 * endpoints, both server-side only) so the integration stays replaceable at
 * this boundary, per the M20 spec. See `docs/paystack.md` and Paystack's own
 * docs: https://paystack.com/docs/api/transaction/.
 *
 * Constructed with one school's own decrypted secret key — never a shared
 * app-wide credential, since each school can hold its own Paystack account.
 * The secret key is used only in the `Authorization` header; it is never
 * logged, returned, or written into any response this class produces.
 */
class PaystackClient
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(private readonly string $secretKey) {}

    /**
     * POST /transaction/initialize. `$payload` must already contain `email`,
     * `amount` (kobo, integer-as-string) and `reference`; `callback_url` and
     * `metadata` are optional. Returns Paystack's `data` object
     * (`authorization_url`, `access_code`, `reference`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws PaystackApiException
     */
    public function initializeTransaction(array $payload): array
    {
        return $this->post('/transaction/initialize', $payload);
    }

    /**
     * GET /transaction/verify/:reference. Returns Paystack's `data` object
     * (`status`, `amount`, `currency`, `reference`, `gateway_response`,
     * `channel`, `paid_at`, `id`, …) — the sole authoritative source of
     * truth for whether a charge actually succeeded.
     *
     * @return array<string, mixed>
     *
     * @throws PaystackApiException
     */
    public function verifyTransaction(string $reference): array
    {
        return $this->get('/transaction/verify/'.rawurlencode($reference));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->client()->post(self::BASE_URL.$path, $payload);
        } catch (\Throwable $e) {
            throw new PaystackApiException("Could not reach Paystack ({$path}).", previous: $e);
        }

        return $this->unwrap($response, $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        try {
            $response = $this->client()->get(self::BASE_URL.$path);
        } catch (\Throwable $e) {
            throw new PaystackApiException("Could not reach Paystack ({$path}).", previous: $e);
        }

        return $this->unwrap($response, $path);
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(Response $response, string $path): array
    {
        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? false) !== true) {
            $message = is_array($body) ? ($body['message'] ?? 'Unknown error') : 'Invalid response';

            throw new PaystackApiException("Paystack request to {$path} failed: {$message}");
        }

        return $body['data'] ?? [];
    }
}
