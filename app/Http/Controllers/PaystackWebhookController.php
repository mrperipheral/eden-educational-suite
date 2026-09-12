<?php

namespace App\Http\Controllers;

use App\Models\PaystackTransaction;
use App\Models\School;
use App\Services\Paystack\PaymentVerificationService;
use App\Services\Paystack\PaystackApiException;
use App\Services\Paystack\UnknownPaystackReferenceException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Paystack's server-to-server webhook (M20, `docs/paystack.md`). Deliberately
 * outside `auth`/`tenant`/`module` — Paystack cannot authenticate as one of
 * our users, so this route is protected by signature verification instead
 * (see `bootstrap/app.php` for its CSRF exemption).
 *
 * **Which school's secret signed this?** Each school can hold its own
 * Paystack account, so there is no single app-wide webhook secret to check
 * against. The reference inside the (as yet unverified) payload is looked
 * up against our own `paystack_transactions` first — a plain read, not a
 * trust decision — to find *whose* transaction this claims to be, and only
 * *that* school's stored secret key is used to verify the signature. An
 * unrecognized reference is acknowledged and ignored before any signature
 * check ever happens, since there is no key to check it against. This is
 * exactly "determine the school from trusted stored data, never from the
 * request" (M20 spec §6) — the request supplies a reference to look up, not
 * a school id to trust.
 *
 * Processing itself is delegated entirely to
 * `App\Services\Paystack\PaymentVerificationService::verifyAndRecord()` —
 * the same idempotent core the browser callback uses — which re-verifies
 * with Paystack's API rather than trusting this payload's own claims.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentVerificationService $verifier,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        $reference = is_array($payload) ? ($payload['data']['reference'] ?? null) : null;

        if (! is_string($reference) || $reference === '') {
            return response('ignored', 200);
        }

        $transaction = PaystackTransaction::withoutGlobalScopes()->where('reference', $reference)->first();

        if ($transaction === null) {
            return response('ignored', 200);
        }

        // This request carries no tenant context at all (no `tenant`
        // middleware ran) — anchor one now, from the transaction's own
        // trusted school_id, before touching anything tenant-scoped
        // (`$school->settings` included).
        $this->tenant->setId($transaction->school_id);

        $school = School::withoutGlobalScopes()->find($transaction->school_id);
        $secretKey = $school?->settings?->paystack_secret_key;
        $signature = (string) $request->header('x-paystack-signature', '');

        if (blank($secretKey) || ! $this->hasValidSignature($rawBody, $signature, $secretKey)) {
            Log::warning('Paystack webhook: signature verification failed.', ['reference' => $reference]);

            return response('invalid signature', 401);
        }

        try {
            $this->verifier->verifyAndRecord($reference);
        } catch (UnknownPaystackReferenceException) {
            return response('ignored', 200);
        } catch (PaystackApiException $e) {
            Log::warning('Paystack webhook: verification call failed, will retry.', ['reference' => $reference]);

            // A 5xx tells Paystack to retry delivery later — the transaction
            // is left `pending`, never guessed at.
            return response('temporarily unavailable', 500);
        }

        return response('ok', 200);
    }

    /** HMAC-SHA512 of the raw request body, keyed with the school's own secret key — Paystack's documented mechanism. */
    private function hasValidSignature(string $rawBody, string $signature, string $secretKey): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secretKey), $signature);
    }
}
