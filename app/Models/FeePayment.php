<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\FeePaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A manually recorded payment (cash / bank transfer / POS / cheque / other —
 * never Paystack; that is M20). School-owned ({@see BelongsToSchool}) *and*
 * scoped to its student. See `docs/fees.md`.
 *
 * `reference` is unique **within the school**. `recorded_by` is **not**
 * mass-assignable (set from the authenticated user by
 * `App\Services\Fees\FeePaymentService`, never from request input). A
 * payment is never edited or deleted once made — a correction goes through
 * {@see self::void()}, which excludes it from balance calculations without
 * erasing the historical row.
 */
class FeePayment extends Model
{
    /** @use HasFactory<FeePaymentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'amount',
        'payment_date',
        'reference',
        'method',
        'payer_name',
        'payer_phone',
        'payer_email',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * @return HasMany<FeePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Sum of this payment's own allocations — never queries `student_fee_charges`. */
    public function allocatedAmount(): string
    {
        $allocations = $this->relationLoaded('allocations') ? $this->allocations : $this->allocations()->get();

        return $allocations->reduce(fn (string $carry, FeePaymentAllocation $a) => bcadd($carry, (string) $a->amount, 2), '0.00');
    }

    /** The part of this payment not yet allocated to any charge (an advance credit). */
    public function unallocatedAmount(): string
    {
        return bcsub((string) $this->amount, $this->allocatedAmount(), 2);
    }

    /** Void this payment — a reversal, never a silent edit of the amount. */
    public function void(User $by, ?string $reason = null): void
    {
        $this->voided_at = Carbon::now();
        $this->voided_by = $by->getKey();
        $this->void_reason = $reason;
        $this->save();
    }

    /**
     * @param  Builder<FeePayment>  $query
     */
    public function scopeForStudent(Builder $query, Student|int $student): void
    {
        $query->where($this->qualifyColumn('student_id'), $student instanceof Student ? $student->getKey() : $student);
    }

    /**
     * @param  Builder<FeePayment>  $query
     */
    public function scopeNotVoided(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('voided_at'));
    }

    /**
     * @param  Builder<FeePayment>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('payment_date'))->orderByDesc($this->qualifyColumn('id'));
    }
}
