<?php

namespace App\Services\Paystack;

use RuntimeException;

/** The active school has not enabled and fully configured Paystack. */
class PaystackNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Online payment is not available for this school.');
    }
}
