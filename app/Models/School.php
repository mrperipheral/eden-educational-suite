<?php

namespace App\Models;

use App\Enums\SchoolStatus;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A school — the tenant root. School-owned models point here via `school_id`
 * (see App\Support\Tenancy\Concerns\BelongsToSchool). This model is NOT itself
 * tenant-scoped.
 *
 * `status` is never mass-assignable; changing it is a platform-admin action.
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
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === SchoolStatus::Active;
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
