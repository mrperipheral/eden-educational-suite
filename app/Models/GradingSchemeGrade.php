<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\GradingSchemeGradeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One grade band within a {@see GradingScheme} ("A" = 70-100, remark
 * "Excellent"). School-owned ({@see BelongsToSchool}) *and* scoped to its
 * scheme. `code` is unique within the scheme. Ranges are validated (0-100,
 * min <= max, no overlap with another active band) in the Form Request —
 * see `App\Http\Requests\Results\GradingSchemeRequest`.
 *
 * The result engine never lets a grade be typed over directly — it always
 * looks one up from a percentage via {@see GradingScheme::gradeFor()}.
 */
class GradingSchemeGrade extends Model
{
    /** @use HasFactory<GradingSchemeGradeFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'min_percentage',
        'max_percentage',
        'remark',
        'position',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GradingScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }

    /**
     * @param  Builder<GradingSchemeGrade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<GradingSchemeGrade>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('min_percentage'));
    }
}
