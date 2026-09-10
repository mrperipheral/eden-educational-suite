<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A subject the school teaches. School-owned ({@see BelongsToSchool}); no
 * subject list is hard-coded (see `docs/academic-foundation.md`).
 *
 * A subject can be offered by any number of {@see AcademicLevel}s via the
 * `level_subject` link — that is the only relationship M8 builds; teacher
 * assignment, timetabling and assessment weighting are later modules.
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'description',
        'position',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The levels that offer this subject.
     *
     * @return BelongsToMany<AcademicLevel, $this>
     */
    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(AcademicLevel::class, 'level_subject')
            ->withPivot('school_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn('name'));
    }
}
