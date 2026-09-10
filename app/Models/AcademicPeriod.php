<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\AcademicPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A division of an {@see AcademicSession} — a term / semester / trimester, named
 * by the school. School-owned ({@see BelongsToSchool}) *and* scoped to its
 * session, so every query is tenant-safe even without the session in the join.
 *
 * `academic_session_id` is set from the parent relation on create and is never
 * mass-assignable or changed afterwards; `school_id` is stamped from the tenant
 * context. `is_current` is managed through {@see self::makeCurrent()}.
 */
class AcademicPeriod extends Model
{
    /** @use HasFactory<AcademicPeriodFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'position',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'position' => 'integer',
            'is_active' => 'boolean',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * Make this the current period *within its session*, demoting any sibling.
     * Also activates it — an inactive current period is a contradiction. The
     * update is tenant-scoped by {@see SchoolScope} and further limited to this
     * period's session.
     */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('academic_session_id', $this->academic_session_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_current' => false]);

            $this->is_current = true;
            $this->is_active = true;
            $this->save();
        });
    }

    /**
     * @param  Builder<AcademicPeriod>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_current'), true);
    }

    /**
     * @param  Builder<AcademicPeriod>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<AcademicPeriod>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'));
    }
}
