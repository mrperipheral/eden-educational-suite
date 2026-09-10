<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AssessmentCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school-configured assessment category ("Classwork", "Test", "Exam", …).
 * School-owned ({@see BelongsToSchool}); no category list is hard-coded — the
 * seeded examples are fully editable (see `docs/assessment-management.md`).
 *
 * `name` and `code` are unique within the school. Categories are deactivated,
 * not deleted, once assessments reference them.
 */
class AssessmentCategory extends Model
{
    /** @use HasFactory<AssessmentCategoryFactory> */
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
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @param  Builder<AssessmentCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * @param  Builder<AssessmentCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'))->orderBy($this->qualifyColumn('name'));
    }
}
