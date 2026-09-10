<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 11 — a teacher's teaching assignment over time. School-owned
     * (`App\Models\TeacherAssignment` uses `BelongsToSchool`) *and* scoped to its
     * teacher, so every query is tenant-safe even without the teacher in the
     * join.
     *
     * One row per assignment: a teacher teaches a subject to a level (optionally
     * a specific arm) for a session (optionally a specific period). History is
     * preserved — an assignment that ends is marked `ended` (`ended_on` set),
     * never deleted. The model is intentionally lean so the future Timetable,
     * Attendance, Assessment and Results modules can extend it.
     *
     * `academic_period_id` and `level_arm_id` are nullable ("where appropriate").
     * The level ↔ arm and session ↔ period pairs, and every id's school
     * ownership, are validated in
     * `App\Http\Requests\Teacher\TeacherAssignmentRequest`. Timetable slots,
     * workload and payroll are **not** built here.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->string('status', 15)->default('active');   // App\Enums\TeacherAssignmentStatus
            $table->date('started_on');
            $table->date('ended_on')->nullable();

            $table->timestamps();

            // "this teacher's assignments" / "…their current ones".
            $table->index(['school_id', 'teacher_id', 'status']);
            // Future "who teaches this class" roster lookups.
            $table->index(
                ['school_id', 'academic_session_id', 'academic_level_id', 'level_arm_id'],
                'teacher_assignments_class_index',
            );
            // "who teaches this subject".
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
