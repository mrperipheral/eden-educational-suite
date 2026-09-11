<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fee structure: "this category costs this amount for this
 * session/period/level(/arm)". School-owned ({@see BelongsToSchool}). Freely
 * editable configuration — a later edit to `amount` (or anything else here)
 * never touches a {@see StudentFeeCharge} already created from it, because a
 * charge snapshots its own amount and context at creation time. See
 * `docs/fees.md`.
 */
class FeeStructure extends Model
{
    /** @use HasFactory<FeeStructureFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'fee_category_id',
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'amount',
        'is_mandatory',
        'is_active',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_mandatory' => true,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FeeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /**
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'academic_level_id');
    }

    /**
     * @return BelongsTo<LevelArm, $this>
     */
    public function arm(): BelongsTo
    {
        return $this->belongsTo(LevelArm::class, 'level_arm_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<StudentFeeCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(StudentFeeCharge::class);
    }

    /**
     * @param  Builder<FeeStructure>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Structures applicable to a given level (and, if given, arm) — an arm
     * on the structure means "this arm only"; a `null` arm on the structure
     * means "the whole level".
     *
     * @param  Builder<FeeStructure>  $query
     */
    public function scopeApplicableTo(Builder $query, int $levelId, ?int $armId): void
    {
        $query->where($this->qualifyColumn('academic_level_id'), $levelId)
            ->where(function (Builder $q) use ($armId) {
                $q->whereNull($this->qualifyColumn('level_arm_id'));
                if ($armId !== null) {
                    $q->orWhere($this->qualifyColumn('level_arm_id'), $armId);
                }
            });
    }

    /**
     * @param  Builder<FeeStructure>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
