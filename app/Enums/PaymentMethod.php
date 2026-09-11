<?php

namespace App\Enums;

use App\Models\FeeCategory;
use App\Models\FeePayment;

/**
 * How a {@see FeePayment} was actually paid (Milestone 19,
 * `docs/fees.md`). A closed, technical classification — unlike
 * {@see FeeCategory} (a school's own naming choice), the payment
 * method is a system concept, so it is a proper enum here. Extensible by
 * adding a case, exactly like every other enum in this codebase.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank transfer'),
            self::Pos => __('POS'),
            self::Cheque => __('Cheque'),
            self::Other => __('Other'),
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
