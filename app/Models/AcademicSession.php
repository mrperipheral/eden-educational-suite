<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A school's academic year — the top of the academic structure
 * (see `docs/academic-foundation.md`). School-owned ({@see BelongsToSchool}):
 * reads, updates and deletes are constrained to the active tenant, and
 * `school_id` is stamped from the context, never from input.
 *
 * A session divides into {@see AcademicPeriod}s (terms / semesters). How many is
 * the school's choice — nothing here assumes three.
 */
class AcademicSession extends Model
{
    /** @use HasFactory<AcademicSessionFactory> */
    use BelongsToSchool, HasFactory;

    /** `is_current` is managed through {@see self::makeCurrent()}, not mass-assigned. */
    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AcademicPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class);
    }

    /**
     * The session's current period, if one is set.
     *
     * @return HasOne<AcademicPeriod, $this>
     */
    public function currentPeriod(): HasOne
    {
        return $this->hasOne(AcademicPeriod::class)->where('is_current', true);
    }

    /**
     * Make this the school's current session, demoting any other. The update
     * query is tenant-scoped by {@see SchoolScope}, so only this school's
     * sessions are touched.
     */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::query()->whereKeyNot($this->getKey())->update(['is_current' => false]);

            $this->is_current = true;
            $this->save();
        });
    }

    /**
     * @param  Builder<AcademicSession>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_current'), true);
    }
}
