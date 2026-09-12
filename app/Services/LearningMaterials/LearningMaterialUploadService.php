<?php

namespace App\Services\LearningMaterials;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded file and creates its `LearningMaterial` row together,
 * atomically. The file is written to disk **first** (using a
 * Laravel-generated random name, never the client's own filename, under a
 * school-id-prefixed folder), then the row is created inside a
 * `DB::transaction()`; if that transaction throws for any reason, the
 * just-stored file is deleted before the exception is rethrown — there is
 * never an orphan file left behind for a row that doesn't exist. See
 * `docs/learning-materials.md`.
 */
class LearningMaterialUploadService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $context  academic_session_id, academic_period_id, academic_level_id, level_arm_id, subject_id, title, description
     *
     * @throws UnsupportedMaterialTypeException
     */
    public function upload(array $context, UploadedFile $file, User $by): LearningMaterial
    {
        $type = LearningMaterialType::fromExtension($file->getClientOriginalExtension());

        // Independent of the Form Request's own MIME whitelist — the
        // server-side reject for a video file (or anything unrecognised)
        // required by the M22 spec, even if the request layer is bypassed.
        if ($type === null || ! $type->isEnabled()) {
            throw new UnsupportedMaterialTypeException(__('This file type is not currently supported.'));
        }

        $schoolId = $this->tenant->idOrFail();
        $path = $file->store("learning-materials/{$schoolId}", LearningMaterial::FILE_DISK);

        try {
            return DB::transaction(function () use ($context, $file, $by, $type, $path) {
                $material = new LearningMaterial($context);
                $material->uploaded_by = $by->getKey();
                $material->file_path = $path;
                $material->file_name = $file->getClientOriginalName();
                $material->file_size = $file->getSize();
                $material->mime_type = $file->getMimeType();
                $material->extension = strtolower($file->getClientOriginalExtension());
                $material->type = $type->value;
                $material->save();

                return $material;
            });
        } catch (\Throwable $e) {
            Storage::disk(LearningMaterial::FILE_DISK)->delete($path);

            throw $e;
        }
    }
}
