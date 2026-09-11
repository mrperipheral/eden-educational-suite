<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — one student's overall result within a {@see result_runs}
     * row (`App\Models\StudentResult`). School-owned **+** scoped to its run.
     *
     * `academic_*` columns are denormalized copies of the run's scope — kept
     * for tenant-scoped indexing and so a student's result history can be
     * queried without joining through the run. `total_percentage` /
     * `average_percentage` are the sum / mean of the student's compiled
     * {@see student_subject_results} percentages; `position` is a competition
     * rank within this run's roster (null when `ranking_enabled` is off).
     * Comments and the attendance snapshot are captured here, once per
     * student per run — no separate attendance table is introduced (M13 stays
     * the source of truth for daily attendance; these four columns are a
     * point-in-time summary, recomputed on every recompile and frozen once the
     * run is approved).
     */
    public function up(): void
    {
        Schema::create('student_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();

            $table->decimal('total_percentage', 7, 2);
            $table->decimal('average_percentage', 5, 2);
            $table->unsignedSmallInteger('subject_count');
            $table->string('overall_grade_code_snapshot', 10)->nullable();
            $table->string('overall_grade_remark_snapshot', 255)->nullable();

            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('class_size');

            $table->text('class_teacher_comment')->nullable();
            $table->text('principal_comment')->nullable();

            $table->unsignedSmallInteger('days_school_opened')->nullable();
            $table->unsignedSmallInteger('days_present')->nullable();
            $table->unsignedSmallInteger('days_absent')->nullable();
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['result_run_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'result_run_id', 'position'], 'student_results_ranking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_results');
    }
};
