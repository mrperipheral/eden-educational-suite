<?php

namespace App\Models;

use App\Enums\PaystackTransactionStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\PaystackTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One online-payment attempt through Paystack. School-owned
 * ({@see BelongsToSchool}) *and* scoped to its student. Deliberately
 * separate from M19's {@see FeePayment} — an attempt can fail or be
 * abandoned and must never become an authoritative payment. See
 * `docs/paystack.md`.
 *
 * `status` / `fee_payment_id` / every provider-response column are **not**
 * mass-assignable — they change only through {@see self::markSuccessful()} /
 * {@see self::markFailed()} / {@see self::markAbandoned()} /
 * {@see self::markVerificationFailed()}, always called from inside a
 * row-locked DB transaction by
 * `App\Services\Paystack\PaymentVerificationService`. `fee_payment_id`
 * being set is itself the idempotency guard — {@see self::isResolved()}.
 */
class PaystackTransaction extends Model
{
    /** @use HasFactory<PaystackTransactionFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'reference',
        'amount',
        'currency',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaystackTransactionStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaystackTransactionStatus::class,
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
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
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<FeePayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function isPending(): bool
    {
        return $this->status === PaystackTransactionStatus::Pending;
    }

    /** Already reached a terminal state — any second attempt to process it must no-op. */
    public function isResolved(): bool
    {
        return ! $this->isPending();
    }

    /**
     * `$providerAttributes` (e.g. `gateway_response`, `channel`,
     * `provider_transaction_id`, `paid_at`) comes only from
     * `PaymentVerificationService`'s own parsing of Paystack's API response
     * — never from request input — so `forceFill()` (bypassing the
     * mass-assignment guard the same way every other internal-only write in
     * this app does) is safe here.
     */
    public function markSuccessful(FeePayment $payment, array $providerAttributes = []): void
    {
        $this->fee_payment_id = $payment->getKey();
        $this->status = PaystackTransactionStatus::Successful;
        $this->verified_at = Carbon::now();
        $this->forceFill($providerAttributes);
        $this->save();
    }

    public function markFailed(?string $reason, array $providerAttributes = []): void
    {
        $this->status = PaystackTransactionStatus::Failed;
        $this->verified_at = Carbon::now();
        $this->failure_reason = $reason;
        $this->forceFill($providerAttributes);
        $this->save();
    }

    public function markAbandoned(?string $reason = null): void
    {
        $this->status = PaystackTransactionStatus::Abandoned;
        $this->verified_at = Carbon::now();
        $this->failure_reason = $reason;
        $this->save();
    }

    public function markVerificationFailed(string $reason, array $providerAttributes = []): void
    {
        $this->status = PaystackTransactionStatus::VerificationFailed;
        $this->verified_at = Carbon::now();
        $this->failure_reason = $reason;
        $this->forceFill($providerAttributes);
        $this->save();
    }

    /** Set once, right after Paystack's initialize call succeeds — see `PaymentInitiationService`. */
    public function recordInitialization(string $authorizationUrl, ?string $accessCode): void
    {
        $this->forceFill([
            'authorization_url' => $authorizationUrl,
            'access_code' => $accessCode,
        ])->save();
    }

    /**
     * @param  Builder<PaystackTransaction>  $query
     */
    public function scopeForStudent(Builder $query, Student|int $student): void
    {
        $query->where($this->qualifyColumn('student_id'), $student instanceof Student ? $student->getKey() : $student);
    }

    /**
     * @param  Builder<PaystackTransaction>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
