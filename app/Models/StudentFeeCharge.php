<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\StudentFeeChargeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One student's fee charge, snapshotted from a {@see FeeStructure} (or
 * entered manually) at creation time. School-owned ({@see BelongsToSchool})
 * *and* scoped to its student. See `docs/fees.md`.
 *
 * `amount` is the immutable original charge — never edited after creation.
 * `discount_amount` / `waived_*` are **not** mass-assignable; they change
 * only through {@see self::applyDiscount()} / {@see self::waive()} /
 * {@see self::unwaive()}. Every monetary calculation here uses `bcmath`
 * (never native float arithmetic) on the `decimal:2`-cast string values.
 * Never hard-deleted.
 */
class StudentFeeCharge extends Model
{
    /** @use HasFactory<StudentFeeChargeFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'enrollment_id',
        'fee_structure_id',
        'fee_category_id',
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'description',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'waived_at' => 'datetime',
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
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * @return BelongsTo<FeeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * @return HasMany<FeePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function isWaived(): bool
    {
        return $this->waived_at !== null;
    }

    /** `amount` minus `discount_amount` — never negative. */
    public function payableAmount(): string
    {
        $payable = bcsub((string) $this->amount, (string) $this->discount_amount, 2);

        return bccomp($payable, '0.00', 2) === -1 ? '0.00' : $payable;
    }

    /**
     * Sum of non-voided payment allocations against this charge — the
     * authoritative "how much has actually been paid", never a
     * client-submitted total. Uses the already-eager-loaded
     * `allocations.payment` when available (statements/lists do this) so
     * rendering many charges never runs a query per charge.
     */
    public function allocatedAmount(): string
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->with('payment:id,voided_at')->get();

        return $allocations
            ->filter(fn (FeePaymentAllocation $a) => $a->payment?->voided_at === null)
            ->reduce(fn (string $carry, FeePaymentAllocation $a) => bcadd($carry, (string) $a->amount, 2), '0.00');
    }

    /** The authoritative outstanding balance — server-calculated, never stored. */
    public function outstandingBalance(): string
    {
        if ($this->isWaived()) {
            return '0.00';
        }

        $remaining = bcsub($this->payableAmount(), $this->allocatedAmount(), 2);

        return bccomp($remaining, '0.00', 2) === -1 ? '0.00' : $remaining;
    }

    public function isFullyPaid(): bool
    {
        return $this->isWaived() || bccomp($this->outstandingBalance(), '0.00', 2) === 0;
    }

    /** How much this charge's waiver is currently forgiving — `0.00` when not waived. */
    public function waivedAmount(): string
    {
        if (! $this->isWaived()) {
            return '0.00';
        }

        $remaining = bcsub($this->payableAmount(), $this->allocatedAmount(), 2);

        return bccomp($remaining, '0.00', 2) === -1 ? '0.00' : $remaining;
    }

    /**
     * Reduce the payable amount by `$amount`. Rejected if it would take the
     * payable amount below what has already been allocated (a charge can
     * never look "overpaid" as a side effect of a later discount).
     */
    public function applyDiscount(User $by, string $amount, ?string $reason = null): void
    {
        $newDiscountTotal = bcadd((string) $this->discount_amount, $amount, 2);
        $newPayable = bcsub((string) $this->amount, $newDiscountTotal, 2);

        if (bccomp($newPayable, $this->allocatedAmount(), 2) === -1) {
            throw new \DomainException('Discount would take the payable amount below what has already been paid.');
        }

        $this->discount_amount = $newDiscountTotal;
        $this->save();
    }

    /** Forgive the remaining balance outright. */
    public function waive(User $by, ?string $reason = null): void
    {
        $this->waived_at = Carbon::now();
        $this->waived_by = $by->getKey();
        $this->waiver_reason = $reason;
        $this->save();
    }

    /** Reverse a waiver — the balance becomes payable again. */
    public function unwaive(): void
    {
        $this->waived_at = null;
        $this->waived_by = null;
        $this->waiver_reason = null;
        $this->save();
    }

    /**
     * @param  Builder<StudentFeeCharge>  $query
     */
    public function scopeForStudent(Builder $query, Student|int $student): void
    {
        $query->where($this->qualifyColumn('student_id'), $student instanceof Student ? $student->getKey() : $student);
    }

    /**
     * @param  Builder<StudentFeeCharge>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
