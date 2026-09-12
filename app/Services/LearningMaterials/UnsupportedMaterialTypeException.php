<?php

namespace App\Services\LearningMaterials;

use RuntimeException;

/**
 * The uploaded file's extension/MIME type is not currently accepted — most
 * importantly, a video file (declared in `App\Enums\LearningMaterialType`
 * but disabled). Thrown by `LearningMaterialUploadService` as a defence-in-
 * depth check independent of the Form Request's own MIME whitelist — never
 * trust a single validation layer alone for "reject this server-side."
 */
class UnsupportedMaterialTypeException extends RuntimeException {}
