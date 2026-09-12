<?php

namespace Tests\Unit\Enums;

use App\Enums\LearningMaterialType;
use Tests\TestCase;

class LearningMaterialTypeTest extends TestCase
{
    public function test_video_is_the_only_disabled_type(): void
    {
        $this->assertFalse(LearningMaterialType::Video->isEnabled());
        $this->assertTrue(LearningMaterialType::Document->isEnabled());
        $this->assertTrue(LearningMaterialType::Image->isEnabled());
        $this->assertTrue(LearningMaterialType::Audio->isEnabled());
    }

    public function test_enabled_excludes_video(): void
    {
        $enabled = LearningMaterialType::enabled();

        $this->assertNotContains(LearningMaterialType::Video, $enabled);
        $this->assertContains(LearningMaterialType::Document, $enabled);
        $this->assertContains(LearningMaterialType::Image, $enabled);
        $this->assertContains(LearningMaterialType::Audio, $enabled);
        $this->assertCount(3, $enabled);
    }

    public function test_enabled_extensions_exclude_every_video_extension(): void
    {
        $extensions = LearningMaterialType::enabledExtensions();

        foreach (LearningMaterialType::Video->extensions() as $videoExtension) {
            $this->assertNotContains($videoExtension, $extensions);
        }

        $this->assertContains('pdf', $extensions);
        $this->assertContains('jpg', $extensions);
        $this->assertContains('jpeg', $extensions);
        $this->assertContains('png', $extensions);
        $this->assertContains('webp', $extensions);
        $this->assertContains('mp3', $extensions);
        $this->assertContains('m4a', $extensions);
        $this->assertContains('wav', $extensions);
    }

    public function test_enabled_mime_types_exclude_every_video_mime_type(): void
    {
        $mimeTypes = LearningMaterialType::enabledMimeTypes();

        foreach (LearningMaterialType::Video->mimeTypes() as $videoMime) {
            $this->assertNotContains($videoMime, $mimeTypes);
        }

        $this->assertContains('application/pdf', $mimeTypes);
        $this->assertContains('image/jpeg', $mimeTypes);
        $this->assertContains('audio/mpeg', $mimeTypes);
    }

    public function test_from_extension_resolves_the_correct_type(): void
    {
        $this->assertSame(LearningMaterialType::Document, LearningMaterialType::fromExtension('pdf'));
        $this->assertSame(LearningMaterialType::Image, LearningMaterialType::fromExtension('JPG'));
        $this->assertSame(LearningMaterialType::Image, LearningMaterialType::fromExtension('.png'));
        $this->assertSame(LearningMaterialType::Audio, LearningMaterialType::fromExtension('wav'));
        $this->assertSame(LearningMaterialType::Video, LearningMaterialType::fromExtension('mp4'));
    }

    public function test_from_extension_is_null_for_an_unrecognised_extension(): void
    {
        $this->assertNull(LearningMaterialType::fromExtension('exe'));
        $this->assertNull(LearningMaterialType::fromExtension('zip'));
        $this->assertNull(LearningMaterialType::fromExtension(''));
    }
}
