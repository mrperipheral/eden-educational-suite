<?php

namespace App\Models;

use App\Enums\PromotionRecordStatus;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\PromotionRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's outcome within a {@see PromotionBatch} — the promotion
 * history. School-owned ({@see BelongsToSchool}) *and* student-scoped. Only
 * ever written by `App\Services\Promotion\PromotionService`, never from
 * request input — nothing here is mass-assignable. See `docs/promotion.md`.
 */
class PromotionRecord extends Model
{
    /** @use HasFactory<PromotionRecordFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromotionRecordStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PromotionBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function sourceEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'source_enrollment_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function targetEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'target_enrollment_id');
    }

    /**
     * @param  Builder<PromotionRecord>  $query
     */
    public function scopeForStudent(Builder $query, Student|int $student): void
    {
        $query->where($this->qualifyColumn('student_id'), $student instanceof Student ? $student->getKey() : $student);
    }

    /**
     * @param  Builder<PromotionRecord>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
