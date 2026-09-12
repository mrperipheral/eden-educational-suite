<?php

namespace App\Models;

use App\Enums\PromotionBatchStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\PromotionBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bulk promotion run — "these students move from this source
 * session/level/arm to this target session/level/arm." School-owned
 * ({@see BelongsToSchool}). See `docs/promotion.md`.
 *
 * `status` is **not** mass-assignable — computed once, after every selected
 * student has been processed, by `App\Services\Promotion\PromotionService`.
 */
class PromotionBatch extends Model
{
    /** @use HasFactory<PromotionBatchFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'source_academic_session_id',
        'source_academic_period_id',
        'source_academic_level_id',
        'source_level_arm_id',
        'target_academic_session_id',
        'target_academic_level_id',
        'target_level_arm_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromotionBatchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'source_academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function sourcePeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'source_academic_period_id');
    }

    /**
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function sourceLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'source_academic_level_id');
    }

    /**
     * @return BelongsTo<LevelArm, $this>
     */
    public function sourceArm(): BelongsTo
    {
        return $this->belongsTo(LevelArm::class, 'source_level_arm_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function targetSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'target_academic_session_id');
    }

    /**
     * @return BelongsTo<AcademicLevel, $this>
     */
    public function targetLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'target_academic_level_id');
    }

    /**
     * @return BelongsTo<LevelArm, $this>
     */
    public function targetArm(): BelongsTo
    {
        return $this->belongsTo(LevelArm::class, 'target_level_arm_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PromotionRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(PromotionRecord::class);
    }

    /**
     * @param  Builder<PromotionBatch>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
