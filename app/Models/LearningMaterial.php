<?php

namespace App\Models;

use App\Enums\LearningMaterialType;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\LearningMaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded file (PDF, image or audio — video is declared via
 * {@see LearningMaterialType} but never accepted yet) shared with a
 * subject + class. School-owned ({@see BelongsToSchool}). See
 * `docs/learning-materials.md`.
 *
 * `type`/`file_path`/`file_name`/`file_size`/`mime_type`/`extension`/
 * `uploaded_by` are **not** mass-assignable — set only by
 * `App\Services\LearningMaterials\LearningMaterialUploadService` at upload
 * time, from the file itself, never from request input directly. There is
 * no draft/status lifecycle: a row is live from the moment it exists.
 */
class LearningMaterial extends Model
{
    /** @use HasFactory<LearningMaterialFactory> */
    use BelongsToSchool, HasFactory;

    /** The disk every material file is stored on — private, never public. */
    public const FILE_DISK = 'local';

    /** @var list<string> */
    protected $fillable = [
        'academic_session_id',
        'academic_period_id',
        'academic_level_id',
        'level_arm_id',
        'subject_id',
        'title',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LearningMaterialType::class,
        ];
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
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileExists(): bool
    {
        return $this->file_path !== null && Storage::disk(self::FILE_DISK)->exists($this->file_path);
    }

    /** Deletes the stored file and the row together — never one without the other. */
    public function deleteWithFile(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(self::FILE_DISK)->delete($this->file_path);
        }

        $this->delete();
    }

    /**
     * @param  Builder<LearningMaterial>  $query
     */
    public function scopeForClass(Builder $query, int $levelId, ?int $armId): void
    {
        $query->where('academic_level_id', $levelId)
            ->where(fn (Builder $q) => $q->whereNull('level_arm_id')->orWhere('level_arm_id', $armId));
    }

    /**
     * @param  Builder<LearningMaterial>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'));
    }
}
