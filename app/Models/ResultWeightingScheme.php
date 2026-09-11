<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ResultWeightingSchemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school-configured result-weighting scheme ("Classwork 30%, Test 30%, Exam
 * 40%"). School-owned ({@see BelongsToSchool}); which {@see AssessmentCategory}
 * rows it uses, and their weights, live on {@see ResultWeightingSchemeItem} — a
 * category not listed simply contributes nothing when this scheme compiles a
 * result. `name` is unique within the school.
 */
class ResultWeightingScheme extends Model
{
    /** @use HasFactory<ResultWeightingSchemeFactory> */
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
     * @return HasMany<ResultWeightingSchemeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ResultWeightingSchemeItem::class);
    }

    /** Sum of this scheme's item weights — must equal 100 to be usable. */
    public function totalWeight(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (float) $items->sum(fn (ResultWeightingSchemeItem $i) => (float) $i->weight_percentage);
    }

    /**
     * @param  Builder<ResultWeightingScheme>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<ResultWeightingScheme>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('name'));
    }
}
