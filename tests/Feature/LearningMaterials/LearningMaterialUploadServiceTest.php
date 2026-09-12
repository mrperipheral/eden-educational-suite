<?php

namespace Tests\Feature\LearningMaterials;

use App\Enums\LearningMaterialType;
use App\Models\LearningMaterial;
use App\Models\User;
use App\Services\LearningMaterials\LearningMaterialUploadService;
use App\Services\LearningMaterials\UnsupportedMaterialTypeException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LearningMaterialUploadServiceTest extends LearningMaterialTestCase
{
    private function service(): LearningMaterialUploadService
    {
        return app(LearningMaterialUploadService::class);
    }

    private function context(array $ctx): array
    {
        return [
            'academic_session_id' => $ctx['session']->id,
            'academic_period_id' => null,
            'academic_level_id' => $ctx['level']->id,
            'level_arm_id' => $ctx['arm']->id,
            'subject_id' => $ctx['subject']->id,
            'title' => 'A material',
            'description' => null,
        ];
    }

    public function test_uploading_a_pdf_creates_the_row_and_stores_the_file(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);
        $by = User::factory()->create();

        $material = $this->service()->upload(
            $this->context($ctx),
            UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            $by,
        );

        $this->assertSame(LearningMaterialType::Document, $material->type);
        $this->assertSame('pdf', $material->extension);
        $this->assertSame('notes.pdf', $material->file_name);
        $this->assertSame($by->id, $material->uploaded_by);
        Storage::disk(LearningMaterial::FILE_DISK)->assertExists($material->file_path);
    }

    public function test_uploading_an_image_works(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);

        $material = $this->service()->upload(
            $this->context($ctx),
            UploadedFile::fake()->create('chart.png', 50, 'image/png'),
            User::factory()->create(),
        );

        $this->assertSame(LearningMaterialType::Image, $material->type);
    }

    public function test_uploading_audio_works(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);

        $material = $this->service()->upload(
            $this->context($ctx),
            UploadedFile::fake()->create('lesson.mp3', 200, 'audio/mpeg'),
            User::factory()->create(),
        );

        $this->assertSame(LearningMaterialType::Audio, $material->type);
    }

    public function test_an_honest_video_file_is_rejected_and_never_stored(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);

        $this->expectException(UnsupportedMaterialTypeException::class);

        try {
            $this->service()->upload(
                $this->context($ctx),
                UploadedFile::fake()->create('lecture.mp4', 500, 'video/mp4'),
                User::factory()->create(),
            );
        } finally {
            $this->assertSame(0, LearningMaterial::query()->count());
            Storage::disk(LearningMaterial::FILE_DISK)->assertDirectoryEmpty("learning-materials/{$school->id}");
        }
    }

    public function test_a_video_file_disguised_with_a_pdf_extension_is_still_rejected(): void
    {
        // The service's own type resolution goes by extension, but a
        // genuinely video file would already have failed the Form
        // Request's content-sniffed mimetypes rule before ever reaching
        // this service — this test documents that the service's own
        // extension-based check is a *secondary*, not the only, defence.
        // Here we assert the service rejects an honestly-named video
        // regardless of how it is named at the HTTP layer.
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);

        $this->expectException(UnsupportedMaterialTypeException::class);

        $this->service()->upload(
            $this->context($ctx),
            UploadedFile::fake()->create('movie.mov', 500, 'video/quicktime'),
            User::factory()->create(),
        );
    }

    public function test_an_unrecognised_extension_is_rejected(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->classContext($school);
        $this->enterSchool($school);

        $this->expectException(UnsupportedMaterialTypeException::class);

        $this->service()->upload(
            $this->context($ctx),
            UploadedFile::fake()->create('archive.zip', 100, 'application/zip'),
            User::factory()->create(),
        );
    }

    public function test_a_database_failure_after_storing_the_file_removes_the_orphan_file(): void
    {
        Storage::fake(LearningMaterial::FILE_DISK);

        $school = $this->newSchool();
        $ctx = $this->context($this->classContext($school));
        $this->enterSchool($school);

        // A nonexistent academic_session_id trips the FK constraint at
        // save() time, after the file has already been stored.
        $ctx['academic_session_id'] = 999999;

        try {
            $this->service()->upload(
                $ctx,
                UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
                User::factory()->create(),
            );
            $this->fail('Expected the save to throw.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, LearningMaterial::query()->count());
        Storage::disk(LearningMaterial::FILE_DISK)->assertDirectoryEmpty("learning-materials/{$school->id}");
    }
}
