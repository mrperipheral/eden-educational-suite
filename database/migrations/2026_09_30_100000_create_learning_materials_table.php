<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 22 — one uploaded file (PDF, image or audio; video is
     * declared but never accepted yet) shared with a subject + class.
     * School-owned (`App\Models\LearningMaterial` uses `BelongsToSchool`).
     *
     * `academic_period_id` and `level_arm_id` are nullable — a material can
     * be scoped to a whole level (every arm) and/or the whole session
     * (not one term), mirroring `promotion_batches`' source/target
     * nullability rather than `assessments`' stricter, always-scoped shape.
     *
     * `type`/`file_path`/`file_name`/`file_size`/`mime_type`/`extension`/
     * `uploaded_by` are **not** mass-assignable — derived from the uploaded
     * file itself and set only by `App\Services\LearningMaterials\
     * LearningMaterialUploadService`. There is no `status`/draft column: a
     * row exists the moment it is uploaded, and is immediately visible to
     * its class (M22 spec: "no drafts, autosave or temporary database
     * records").
     */
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('type', 20); // App\Enums\LearningMaterialType
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'learning_materials_scope_index');
            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'learning_materials_class_index');
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_materials');
    }
};
