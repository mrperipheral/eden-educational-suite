<?php

namespace Database\Factories;

use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\StudentFeeCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeePaymentAllocation>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `created_by` is not
 * `$fillable`; set explicitly via the factory definition.
 */
class FeePaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_payment_id' => FeePayment::factory(),
            'student_fee_charge_id' => StudentFeeCharge::factory(),
            'created_by' => User::factory(),
            'amount' => 1000,
        ];
    }
}
