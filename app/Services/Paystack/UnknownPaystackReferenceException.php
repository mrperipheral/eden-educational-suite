<?php

namespace App\Services\Paystack;

use App\Models\PaystackTransaction;
use RuntimeException;

/**
 * A reference was presented (via the browser callback or a webhook) that
 * does not match any locally-initiated {@see PaystackTransaction}.
 * Never fabricate a payment for it — this is either a stale/foreign event or
 * a manipulation attempt, and both are handled the same way: acknowledged,
 * never acted on.
 */
class UnknownPaystackReferenceException extends RuntimeException
{
    public function __construct(string $reference)
    {
        parent::__construct("Unknown Paystack reference: {$reference}");
    }
}
