<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 14 — a gradeable assessment for one class in one subject.
     * School-owned (`App\Models\Assessment` uses `BelongsToSchool`); `school_id`
     * is stamped from the tenant context and is never user-editable.
     *
     * The academic context (session + period + level + arm + subject) is fixed
     * at creation, like a timetable's session. `max_score` is the denominator
     * for every {@see assessment_scores} row. `status`
     * (`App\Enums\AssessmentStatus`) runs `draft → published → locked`;
     * `published_at` / `locked_at` / `locked_by` / `created_by` are captured for
     * future auditing and are **not** mass-assignable.
     *
     * `assignment_id` optionally links the assessment to the {@see assignments}
     * row it grades — M14 stores the link only, it computes nothing from it.
     *
     * No final grades / percentages / averages / positions are stored here —
     * those are M15's to calculate from this source data.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->date('assessment_date');
            $table->decimal('max_score', 6, 2);
            $table->text('instructions')->nullable();
            $table->string('status', 15)->default('draft');   // App\Enums\AssessmentStatus
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'assessments_scope_index');
            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'assessments_class_index');
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'assessment_category_id'], 'assessments_category_index');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'assessment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
