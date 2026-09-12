<?php

namespace App\Enums;

use App\Models\LearningMaterial;

/**
 * The kind of file a {@see LearningMaterial} carries (Milestone
 * 22, `docs/learning-materials.md`). A coarse category derived automatically
 * from the uploaded file's extension — never chosen by the uploader — used
 * for display/icons and for the enabled-type whitelist.
 *
 * `Video` is declared as a **future-ready, currently disabled** case: it has
 * real extensions/MIME types defined (so nothing needs to change shape when
 * it is switched on) but {@see self::isEnabled()} excludes it from every
 * upload path — the Form Request's MIME whitelist, the create-form's type
 * options, and the service layer's own defence-in-depth check all derive
 * from {@see self::enabled()}. Enabling video later is flipping
 * `isEnabled()`, nothing else.
 */
enum LearningMaterialType: string
{
    case Document = 'document';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Document => __('Document'),
            self::Image => __('Image'),
            self::Audio => __('Audio'),
            self::Video => __('Video'),
        };
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Document => ['pdf'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp'],
            self::Audio => ['mp3', 'm4a', 'wav'],
            self::Video => ['mp4', 'mov', 'avi', 'webm'],
        };
    }

    /**
     * MIME types accepted for this category — deliberately generous per
     * format (different systems/browsers report audio/video MIME types
     * inconsistently) since the extension list above is the authoritative
     * whitelist; this is the second, independent check.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Document => ['application/pdf'],
            self::Image => ['image/jpeg', 'image/png', 'image/webp'],
            self::Audio => ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/wave'],
            self::Video => ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'],
        };
    }

    /** Whether uploads of this type are accepted yet. Only `Video` is disabled. */
    public function isEnabled(): bool
    {
        return $this !== self::Video;
    }

    /** @return list<self> */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isEnabled()));
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Every extension accepted across the currently-enabled types.
     *
     * @return list<string>
     */
    public static function enabledExtensions(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (self $type) => $type->extensions(), self::enabled()))));
    }

    /**
     * Every MIME type accepted across the currently-enabled types.
     *
     * @return list<string>
     */
    public static function enabledMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (self $type) => $type->mimeTypes(), self::enabled()))));
    }

    /**
     * Resolve the type for a given file extension (case-insensitive), among
     * **all** declared types — including the disabled `Video` one, so a
     * caller can tell "this is a video" apart from "this is not a
     * recognised file at all" and reject each with an appropriate message.
     */
    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower(ltrim($extension, '.'));

        foreach (self::cases() as $type) {
            if (in_array($extension, $type->extensions(), true)) {
                return $type;
            }
        }

        return null;
    }
}
