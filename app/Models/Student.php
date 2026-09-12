<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A student. School-owned ({@see BelongsToSchool}): every query is constrained
 * to the active tenant, `school_id` is stamped from the context and is never in
 * `$fillable` / read from input. See `docs/student-management.md`.
 *
 * The student's current class is **not** an attribute here — it is the single
 * `Enrollment` with `status = active` ({@see self::currentEnrollment()}).
 * Students are never deleted; leaving is a `status` change.
 *
 * `user_id` (M17) is a **nullable** link to an existing application account —
 * the same pattern as {@see Guardian}/`Teacher`'s `user_id`: set only through
 * `StudentController::updateUser()`, never mass-assigned. A student is a
 * person record first; whether they can sign in to the Student Portal is a
 * separate, later concern. See `docs/student-portal.md`.
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'date_of_birth',
        'gender',
        'admission_number',
        'admitted_on',
        'contact_email',
        'contact_phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'notes',
    ];

    /**
     * `status` is not mass-assignable — it changes only through the dedicated
     * status endpoint (`StudentController::updateStatus()`). The
     * `graduated_*` columns (M21, `docs/promotion.md`) are likewise not
     * mass-assignable — set only by `App\Services\Promotion\GraduationService`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => StudentStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admitted_on' => 'date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'graduated_at' => 'datetime',
        ];
    }

    /**
     * The application account this student record is linked to, if any. Not
     * automatically a Student Portal login — this stays `null` until an admin
     * links an existing member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The student's current placement (the one `active` enrollment), if any.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->where('status', EnrollmentStatus::Active->value);
    }

    /**
     * The student's parents / guardians, with the relationship type and
     * primary-contact flag from the pivot.
     *
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * The raw link rows — used when adding / editing / removing a relationship.
     *
     * @return HasMany<GuardianStudent, $this>
     */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(GuardianStudent::class);
    }

    /**
     * This student's fee charges (M19, `docs/fees.md`).
     *
     * @return HasMany<StudentFeeCharge, $this>
     */
    public function feeCharges(): HasMany
    {
        return $this->hasMany(StudentFeeCharge::class);
    }

    /**
     * This student's recorded payments (M19, `docs/fees.md`).
     *
     * @return HasMany<FeePayment, $this>
     */
    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    /**
     * This student's promotion history (M21, `docs/promotion.md`).
     *
     * @return HasMany<PromotionRecord, $this>
     */
    public function promotionRecords(): HasMany
    {
        return $this->hasMany(PromotionRecord::class);
    }

    /**
     * The session this student graduated in, if any (M21).
     *
     * @return BelongsTo<AcademicSession, $this>
     */
    public function graduatedSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'graduated_academic_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function graduatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graduated_by');
    }

    public function isGraduated(): bool
    {
        return $this->status === StudentStatus::Graduated;
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]));
    }

    /** First + last only — for compact lists. */
    public function shortName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** The name to greet the student by. */
    public function displayName(): string
    {
        return $this->preferred_name ?: $this->first_name;
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('last_name'))->orderBy($this->qualifyColumn('first_name'));
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where($this->qualifyColumn('first_name'), 'like', $like)
                ->orWhere($this->qualifyColumn('last_name'), 'like', $like)
                ->orWhere($this->qualifyColumn('preferred_name'), 'like', $like)
                ->orWhere($this->qualifyColumn('admission_number'), 'like', $like);
        });
    }
}
