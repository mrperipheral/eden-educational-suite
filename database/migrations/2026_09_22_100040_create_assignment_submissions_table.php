<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 14 — whether one student has turned in an {@see assignments} row.
     * School-owned (`App\Models\AssignmentSubmission` uses `BelongsToSchool`)
     * *and* scoped to its assignment.
     *
     * `status` (`App\Enums\AssignmentSubmissionStatus`) defaults to `pending` —
     * the completion roster is materialised when the assignment is created, so
     * every eligible student starts pending and the teacher moves them on. This
     * tracks **completion only**; it holds no score.
     *
     * `unique(assignment_id, student_id)` — one row per student per assignment.
     * Rows are historical and are never deleted for status/enrolment changes.
     *
     * Deliberately minimal: no file upload, no student-facing submission flow,
     * no plagiarism / auto-grading (all deferred — see `docs/assessment-management.md`).
     */
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('status', 15)->default('pending');   // App\Enums\AssignmentSubmissionStatus
            $table->date('submitted_on')->nullable();
            $table->string('remark', 500)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'assignment_id', 'status'], 'assignment_submissions_roster_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
