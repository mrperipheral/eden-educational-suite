<?php

namespace App\Models;

use App\Enums\TeacherAssignmentStatus;
use App\Enums\TeacherStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teacher. School-owned ({@see BelongsToSchool}): every query is constrained
 * to the active tenant, `school_id` is stamped from the context and is never in
 * `$fillable` / read from input. See `docs/teacher-management.md`.
 *
 * The record is professional-only — minimal contact data, no identity /
 * financial / medical fields, no credentials. `user_id` optionally links the
 * record to an existing application account; it is set only through the
 * dedicated endpoint, never mass-assigned. `status`
 * ({@see TeacherStatus}) is likewise not mass-assignable — a teacher who leaves
 * is `resigned`, never deleted.
 */
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'employee_number',
        'email',
        'phone',
        'employed_on',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'notes',
    ];

    /**
     * `status` and `user_id` are not mass-assignable — `status` changes through
     * `TeacherController::updateStatus()`, `user_id` through
     * `TeacherController::updateUser()`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TeacherStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employed_on' => 'date',
            'status' => TeacherStatus::class,
        ];
    }

    /**
     * The application account this teacher record is linked to, if any. A
     * teacher is not automatically a login — this stays `null` until an admin
     * links an existing member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /**
     * The teacher's current (active) assignments.
     *
     * @return HasMany<TeacherAssignment, $this>
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class)->where('status', TeacherAssignmentStatus::Active->value);
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

    /** The name to address the teacher by. */
    public function displayName(): string
    {
        return $this->preferred_name ?: $this->first_name;
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('last_name'))->orderBy($this->qualifyColumn('first_name'));
    }

    /**
     * @param  Builder<Teacher>  $query
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
                ->orWhere($this->qualifyColumn('employee_number'), 'like', $like)
                ->orWhere($this->qualifyColumn('email'), 'like', $like);
        });
    }
}
