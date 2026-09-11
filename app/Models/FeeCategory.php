<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\FeeCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school-configured fee category ("Tuition", "Registration", "Books", …).
 * School-owned ({@see BelongsToSchool}); no category list is hard-coded — the
 * seeded examples are fully editable (see `docs/fees.md`). Mirrors
 * `App\Models\AssessmentCategory` (M14) exactly.
 *
 * `name` and `code` are unique within the school. Categories are deactivated,
 * not deleted, once fee structures / charges reference them.
 */
class FeeCategory extends Model
{
    /** @use HasFactory<FeeCategoryFactory> */
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
     * @return HasMany<FeeStructure, $this>
     */
    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    /**
     * @return HasMany<StudentFeeCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(StudentFeeCharge::class);
    }

    /**
     * @param  Builder<FeeCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<FeeCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn('name'));
    }
}
