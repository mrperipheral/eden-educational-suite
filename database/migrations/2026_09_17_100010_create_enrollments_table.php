<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 9 — a student's academic placement over time. School-owned
     * (`App\Models\Enrollment` uses `BelongsToSchool`) *and* scoped to its
     * student, so every query is tenant-safe even without the student in the
     * join.
     *
     * One row per placement. History is preserved: a student who changes class
     * gets a new row; the old one is closed (`status = completed`, `ended_on`
     * set), never deleted. At most one `active` enrollment per student — the
     * "current class" — enforced in `Enrollment::makeActive()`.
     *
     * `academic_period_id` and `level_arm_id` are nullable ("where appropriate"
     * / "where applicable"). The level ↔ arm and session ↔ period pairs are
     * validated for consistency in `App\Http\Requests\Student\EnrollmentRequest`.
     * Promotion / bulk roll-over is **not** built here.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 15)->default('active');   // App\Enums\EnrollmentStatus
            $table->date('started_on');
            $table->date('ended_on')->nullable();

            $table->timestamps();

            // "this student's history" / "this student's current placement".
            $table->index(['school_id', 'student_id', 'status']);
            // Future roster lookups ("everyone in this level/arm this session").
            $table->index(['school_id', 'academic_session_id', 'academic_level_id', 'level_arm_id'], 'enrollments_roster_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
