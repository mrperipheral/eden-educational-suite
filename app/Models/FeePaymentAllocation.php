<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\FeePaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of a {@see FeePayment} is applied to one {@see StudentFeeCharge}.
 * School-owned ({@see BelongsToSchool}). Every write goes through
 * `App\Services\Fees\FeePaymentService` inside a DB transaction with row
 * locks — never created directly from request input. `created_by` is not
 * mass-assignable. Never hard-deleted; a correction voids the payment
 * instead (see {@see FeePayment::void()}).
 */
class FeePaymentAllocation extends Model
{
    /** @use HasFactory<FeePaymentAllocationFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'fee_payment_id',
        'student_fee_charge_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<FeePayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    /**
     * @return BelongsTo<StudentFeeCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(StudentFeeCharge::class, 'student_fee_charge_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
