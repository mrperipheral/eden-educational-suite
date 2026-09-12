<?php

namespace App\Enums;

/**
 * An online-payment attempt's lifecycle (Milestone 20, `docs/paystack.md`).
 *
 * `Pending` — initialized with Paystack, awaiting the customer to complete
 * (or abandon) checkout.
 * `Successful` — verified server-side (amount + currency matched); the
 * authoritative M19 `App\Models\FeePayment` has been created.
 * `Failed` — Paystack reports the charge itself failed (declined, etc).
 * `Abandoned` — the customer never completed checkout.
 * `VerificationFailed` — Paystack reports success but our own checks
 * (amount/currency/ownership) did not match — treated as unresolved, never
 * silently accepted; a genuine mismatch here is a red flag, not a payment.
 *
 * Only `App\Services\Paystack\PaymentVerificationService`, inside a
 * row-locked DB transaction, ever moves a transaction out of `Pending` — no
 * route lets an ordinary user set this directly.
 */
enum PaystackTransactionStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
    case VerificationFailed = 'verification_failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Successful => __('Successful'),
            self::Failed => __('Failed'),
            self::Abandoned => __('Abandoned'),
            self::VerificationFailed => __('Verification failed'),
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Successful => 'success',
            self::Failed, self::VerificationFailed => 'danger',
            self::Abandoned => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
