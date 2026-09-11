<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\GradingSchemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school-configured grading scheme ("A = 70-100, B = 60-69, ...").
 * School-owned ({@see BelongsToSchool}); no grade band is hard-coded — see
 * {@see GradingSchemeGrade}. `name` is unique within the school. Schemes are
 * deactivated, not deleted, once a {@see ResultRun} references them.
 */
class GradingScheme extends Model
{
    /** @use HasFactory<GradingSchemeFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GradingSchemeGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(GradingSchemeGrade::class);
    }

    /**
     * The active grade band a percentage falls into, if any (0-100 inclusive
     * bounds on both ends of each band).
     */
    public function gradeFor(float $percentage): ?GradingSchemeGrade
    {
        $grades = $this->relationLoaded('grades') ? $this->grades : $this->grades()->active()->ordered()->get();

        return $grades->first(
            fn (GradingSchemeGrade $g) => $g->is_active && $percentage >= (float) $g->min_percentage && $percentage <= (float) $g->max_percentage,
        );
    }

    /**
     * @param  Builder<GradingScheme>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<GradingScheme>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('name'));
    }
}
