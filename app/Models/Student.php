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
     * status endpoint (`StudentController::updateStatus()`).
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
        ];
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
