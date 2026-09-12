<?php

namespace App\Services\Paystack;

use RuntimeException;

/**
 * Paystack's API returned a non-2xx response, `status: false`, or the
 * request could not be completed at all (network failure, timeout). Callers
 * must treat this as "ambiguous — do not guess" (see `docs/paystack.md`):
 * never mark a transaction failed just because *our* call to Paystack
 * failed, since the charge itself may still have succeeded.
 */
class PaystackApiException extends RuntimeException {}
