<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — a school-scoped online examination
     * (`App\Models\Examination`). The academic context (session + period +
     * level + arm + subject) is **fully required and fixed at creation**,
     * mirroring `assessments` exactly — an exam is a single class's exam,
     * not a "whole level" broadcast like `learning_materials`.
     *
     * `status` (`App\Enums\ExaminationStatus`) is one-way: draft → scheduled
     * → closed, never mass-assignable. `result_release` /
     * `result_release_at` (`App\Enums\ResultReleaseMode`) control when a
     * *completed* attempt's score becomes visible to the student who sat
     * it — never whether correct answers are exposed (that is never
     * automatic in M23). `pass_mark_percentage` is a percentage, not raw
     * marks, so it stays meaningful even as questions are added/removed
     * while still in `draft`.
     */
    public function up(): void
    {
        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('pass_mark_percentage', 5, 2)->default(50);
            $table->string('status', 15)->default('draft'); // App\Enums\ExaminationStatus
            $table->string('result_release', 15)->default('immediate'); // App\Enums\ResultReleaseMode
            $table->dateTime('result_release_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'examinations_scope_index');
            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'examinations_class_index');
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'starts_at', 'ends_at'], 'examinations_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examinations');
    }
};
