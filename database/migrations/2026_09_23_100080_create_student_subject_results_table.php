<?php

use App\Models\ResultAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — one student's compiled result for one subject within a
     * {@see result_runs} row (`App\Models\StudentSubjectResult`). School-owned
     * **+** scoped to its run. The category-by-category breakdown (the "raw
     * assessment components" — CA1, CA2, Exam, ...) lives on the child table
     * {@see student_subject_result_components}; this row holds the composite
     * result.
     *
     * `percentage` is the weighted composite (0-100, since a weighting
     * scheme's active items are validated to sum to 100 — "weighted total" and
     * "percentage" are therefore the same figure, so only one column is
     * stored). `grade_*_snapshot` and `subject_position` are computed at
     * compile time; `grading_scheme_grade_id` is an informational link only —
     * the snapshot strings are authoritative and survive the grading scheme
     * changing later.
     *
     * `is_adjusted` / `adjusted_*` are stamped only by an applied
     * {@see ResultAdjustment} — never by a normal recompile.
     */
    public function up(): void
    {
        Schema::create('student_subject_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();

            $table->decimal('percentage', 5, 2);
            $table->foreignId('grading_scheme_grade_id')->nullable()->constrained()->nullOnDelete();
            $table->string('grade_code_snapshot', 10)->nullable();
            $table->string('grade_remark_snapshot', 255)->nullable();
            $table->unsignedSmallInteger('subject_position')->nullable();

            $table->boolean('is_adjusted')->default(false);
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();

            $table->timestamps();

            $table->unique(['result_run_id', 'student_id', 'subject_id'], 'student_subject_results_unique');
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'result_run_id', 'subject_id'], 'student_subject_results_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_results');
    }
};
