<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — which predefined fields a school's report card shows
     * (`App\Models\ReportCardConfiguration`). School-owned. Each field is its
     * own **typed boolean column** — not an opaque JSON blob — so the set stays
     * simple, queryable and matches every other school-owned table's
     * conventions. There is no drag-and-drop layout builder: display order
     * within a section is fixed by definition order; only visibility is
     * configurable.
     *
     * Scope: `academic_session_id` / `academic_period_id` are both nullable.
     * `(school_id, academic_session_id, academic_period_id)` is unique so at
     * most one *scope* row exists per scope; precedence (most specific wins)
     * and the "no row yet -> code defaults" fallback are resolved in
     * `App\Models\ReportCardConfiguration::forScope()`, not the database.
     *
     * `result_run_id` marks a different kind of row entirely: a **frozen
     * snapshot** of the field toggles that were in effect when one
     * `App\Models\ResultRun` was published — see
     * `App\Models\ResultRun::snapshotReportCardConfiguration()`. Editing a
     * scope row after a run is published never rewrites that run's snapshot
     * row, satisfying "changing the configuration must not rewrite a locked
     * historical report card." Signature *images* are deliberately **not**
     * snapshotted (see the model) — only the show/hide toggles are frozen.
     *
     * `principal_signature_path` / `class_teacher_signature_path` follow the
     * exact private-disk pattern M6 uses for the school logo
     * (`App\Models\SchoolSetting::LOGO_DISK`) — never web-served directly,
     * streamed through a gated route.
     */
    public function up(): void
    {
        Schema::create('report_card_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('result_run_id')->nullable()->unique()->constrained()->cascadeOnDelete();

            $table->string('name')->default('Default');

            // Student information
            $table->boolean('show_student_name')->default(true);
            $table->boolean('show_admission_number')->default(true);
            $table->boolean('show_student_photo')->default(true);
            $table->boolean('show_class_level')->default(true);
            $table->boolean('show_class_arm')->default(true);
            $table->boolean('show_session')->default(true);
            $table->boolean('show_term')->default(true);

            // Academic results
            $table->boolean('show_subject_components')->default(true);
            $table->boolean('show_subject_percentage')->default(true);
            $table->boolean('show_subject_grade')->default(true);
            $table->boolean('show_subject_remark')->default(true);
            $table->boolean('show_subject_position')->default(true);

            // Overall performance
            $table->boolean('show_overall_total')->default(true);
            $table->boolean('show_overall_average')->default(true);
            $table->boolean('show_overall_position')->default(true);
            $table->boolean('show_overall_class_size')->default(true);

            // Attendance (see App\Models\StudentResult's snapshot columns)
            $table->boolean('show_attendance_days_opened')->default(true);
            $table->boolean('show_attendance_days_present')->default(true);
            $table->boolean('show_attendance_days_absent')->default(true);
            $table->boolean('show_attendance_percentage')->default(true);

            // Comments
            $table->boolean('show_class_teacher_comment')->default(true);
            $table->boolean('show_principal_comment')->default(true);

            // Signatures
            $table->boolean('show_class_teacher_signature')->default(true);
            $table->boolean('show_principal_signature')->default(true);
            $table->string('principal_signature_path')->nullable();
            $table->string('class_teacher_signature_path')->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'academic_session_id', 'academic_period_id'], 'report_card_config_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_configurations');
    }
};
