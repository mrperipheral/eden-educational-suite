<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\SchoolStatus;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A school — the tenant root. School-owned models point here via `school_id`
 * (see App\Support\Tenancy\Concerns\BelongsToSchool). This model is NOT itself
 * tenant-scoped.
 *
 * `status` is never mass-assignable; changing it is a platform-admin action.
 * A new school is provisioned by App\Services\SchoolProvisioner.
 */
#[Fillable(['name', 'slug'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SchoolStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
        ];
    }

    /**
     * Members of this school. The pivot carries the per-school `role`.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(SchoolUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasOne<SchoolSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(SchoolSetting::class);
    }

    /**
     * @return HasMany<AcademicSession, $this>
     */
    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    /**
     * @return HasMany<AcademicLevel, $this>
     */
    public function academicLevels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class);
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<Guardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /**
     * @return HasMany<Teacher, $this>
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * @return HasMany<Timetable, $this>
     */
    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    public function isActive(): bool
    {
        return $this->status === SchoolStatus::Active;
    }

    /** Whether at least one member holds the School Admin role. */
    public function hasSchoolAdmin(): bool
    {
        return $this->users()->wherePivot('role', Role::SchoolAdmin->value)->exists();
    }

    /**
     * @param  Builder<School>  $query
     */
    public function scopeActive(Builder $query): void
    {
        // Qualified so it is safe when called through the `school_user` join
        // (e.g. `$user->schools()->active()`).
        $query->where($this->qualifyColumn('status'), SchoolStatus::Active->value);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
