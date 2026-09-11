<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePayment>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `recorded_by` is not
 * `$fillable`; set explicitly via the factory definition.
 */
class FeePaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $n = ++$counter;

        return [
            'student_id' => Student::factory(),
            'recorded_by' => User::factory(),
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
            'reference' => 'RCPT-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'method' => PaymentMethod::Cash->value,
        ];
    }
}
