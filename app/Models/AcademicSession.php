<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A school's academic year. School-owned ({@see BelongsToSchool}) — reads,
 * updates and deletes are constrained to the active tenant, and `school_id` is
 * stamped from the context, never from input.
 *
 * Structure beyond "a named date range" (terms, holidays, the academic
 * calendar) belongs to the Academic Management milestone.
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
     * Make this the school's current session, demoting any other. The update
     * query is tenant-scoped by {@see SchoolScope}, so only
     * this school's sessions are touched.
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
