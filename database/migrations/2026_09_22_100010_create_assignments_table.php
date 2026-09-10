<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 14 — a piece of set work for a class in a subject. School-owned
     * (`App\Models\Assignment` uses `BelongsToSchool`); `school_id` is stamped
     * from the tenant context.
     *
     * An assignment is tied to the academic context (session + period + level +
     * arm + subject) and has an assigned/due date. `status`
     * (`App\Enums\AssignmentStatus`) runs `draft → published → closed`. It is
     * owned by the `Teacher` who created it (`teacher_id`, nullable so an admin
     * without a teacher record can still create one) and by the acting user
     * (`created_by`). It carries **no scores** — grading is an `Assessment`.
     *
     * `assessment_scores` / report-card compilation are **not** built here.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('assigned_on');
            $table->date('due_on');
            $table->decimal('max_score', 6, 2)->nullable();
            $table->string('status', 15)->default('draft');   // App\Enums\AssignmentStatus
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'assignments_scope_index');
            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'assignments_class_index');
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'teacher_id']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
