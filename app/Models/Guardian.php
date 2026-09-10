<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A parent / guardian. School-owned ({@see BelongsToSchool}): every query is
 * constrained to the active tenant, `school_id` is stamped from the context and
 * is never in `$fillable` / read from input. See `docs/guardian-management.md`.
 *
 * Contact data only — no portal credentials, no government ID, no financial /
 * medical / emergency information. The student ↔ guardian relationship (with its
 * type and primary-contact flag) lives on the `guardian_student` link
 * ({@see GuardianStudent}).
 */
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'email',
        'phone',
        'alt_phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'notes',
    ];

    /**
     * The students this guardian is linked to, with the relationship type and
     * primary-contact flag from the pivot.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * The raw link rows — used when adding / editing / removing a relationship.
     *
     * @return HasMany<GuardianStudent, $this>
     */
    public function studentLinks(): HasMany
    {
        return $this->hasMany(GuardianStudent::class);
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

    /** The name to address the guardian by. */
    public function displayName(): string
    {
        return $this->preferred_name ?: $this->first_name;
    }

    /**
     * @param  Builder<Guardian>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('last_name'))->orderBy($this->qualifyColumn('first_name'));
    }

    /**
     * @param  Builder<Guardian>  $query
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
                ->orWhere($this->qualifyColumn('email'), 'like', $like)
                ->orWhere($this->qualifyColumn('phone'), 'like', $like)
                ->orWhere($this->qualifyColumn('alt_phone'), 'like', $like);
        });
    }
}
