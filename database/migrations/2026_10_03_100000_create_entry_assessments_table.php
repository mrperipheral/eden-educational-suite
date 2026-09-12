<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 25 — a record of an assessment conducted for a prospective
     * or newly admitted student (`App\Models\EntryAssessment`). School-owned;
     * `school_id` is stamped from the tenant context and is never
     * user-editable. See `docs/entry-placement-assessment.md`.
     *
     * `candidate_name` is always stored explicitly — a self-contained
     * historical record, independent of whether (or when) the candidate is
     * later linked to a `Student` row or that student's name later changes.
     * `student_id` is a nullable, optional link (`nullOnDelete` — a student
     * is never hard-deleted anyway, but the FK stays defensive).
     *
     * `academic_level_id` (required) + `level_arm_id` (nullable — the arm
     * may not be decided yet) are the assessed/intended class; `subject_id`
     * is required — one record per subject assessed, so a candidate tested
     * in several subjects gets several rows (no batch/grouping entity
     * introduced; they share `candidate_name` + `admission_reference` +
     * `assessed_on`).
     *
     * `score` is nullable (not yet entered/conducted); `max_score` is
     * required. `percentage` is deliberately **not** stored — it is always
     * safely derivable from `score`/`max_score` at read time. `result` is a
     * free-text, school-defined outcome label (e.g. "Pass"/"Fail") — not an
     * enum, since the vocabulary a school wants here is theirs to choose,
     * and never drives any automatic placement action.
     *
     * `assessor_id` is captured from the authenticated user at creation —
     * never a request-supplied name, mirroring `assessments.created_by` /
     * `learning_materials.uploaded_by`. `status`
     * (`App\Enums\EntryAssessmentStatus`) is Active/Archived only — the
     * row's own retention lifecycle; the row is never hard-deleted.
     */
    public function up(): void
    {
        Schema::create('entry_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();

            $table->string('candidate_name', 150);
            $table->string('admission_reference', 100)->nullable();

            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->date('assessed_on');
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2);
            $table->string('result', 50)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('active'); // App\Enums\EntryAssessmentStatus

            $table->timestamps();

            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'entry_assessments_class_index');
            $table->index(['school_id', 'subject_id']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'assessed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_assessments');
    }
};
