<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AcademicLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An academic level / class band, defined entirely by the school
 * (see `docs/academic-foundation.md`). School-owned ({@see BelongsToSchool}).
 *
 * A level has {@see LevelArm}s (streams) and offers a set of {@see Subject}s
 * (the `level_subject` link — no teacher / timetable / enrolment here).
 */
class AcademicLevel extends Model
{
    /** @use HasFactory<AcademicLevelFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
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
     * @return HasMany<LevelArm, $this>
     */
    public function arms(): HasMany
    {
        return $this->hasMany(LevelArm::class);
    }

    /**
     * The subjects this level offers.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'level_subject')
            ->withPivot('school_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<AcademicLevel>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<AcademicLevel>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'));
    }
}
