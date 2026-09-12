<?php

namespace Database\Factories;

use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaystackTransaction>
 *
 * `school_id` is stamped by the BelongsToSchool trait from the active tenant
 * context — tests must `enterSchool()` first. `initiated_by` is not
 * `$fillable`; set explicitly via the factory definition.
 */
class PaystackTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'initiated_by' => User::factory(),
            'reference' => 'PSK-'.Str::upper(Str::random(12)),
            'amount' => '5000.00',
            'currency' => 'NGN',
        ];
    }
}
