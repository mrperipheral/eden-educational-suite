<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\ResultWeightingSchemeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assessment-category weight within a {@see ResultWeightingScheme}.
 * School-owned ({@see BelongsToSchool}) *and* scoped to its scheme. A category
 * appears at most once per scheme (`unique(scheme, category)`).
 */
class ResultWeightingSchemeItem extends Model
{
    /** @use HasFactory<ResultWeightingSchemeItemFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'assessment_category_id',
        'weight_percentage',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_percentage' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ResultWeightingScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ResultWeightingScheme::class, 'result_weighting_scheme_id');
    }

    /**
     * @return BelongsTo<AssessmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'assessment_category_id');
    }
}
