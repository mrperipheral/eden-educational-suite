<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — a reusable, tenant-scoped question definition
     * (`App\Models\Question`). Deliberately minimal: no full M24 Question
     * Bank metadata (topic tags, difficulty, author history) yet, but the
     * shape (school + subject scoped, freely editable, `is_active` toggle)
     * is compatible with M24 growing it later without a breaking change.
     *
     * A `Question` is never read directly by a live/historical examination
     * — `App\Models\ExaminationQuestion` snapshots its content the moment
     * it is attached to an exam, so editing a question here never alters
     * an exam that already uses it. See `docs/cbt.md` §5–6.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->text('question_text');
            $table->string('type', 20); // App\Enums\ExaminationQuestionType
            $table->decimal('marks', 6, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
