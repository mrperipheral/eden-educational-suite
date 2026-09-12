<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — one student's timed attempt at an examination
     * (`App\Models\ExamAttempt`). School-owned **and** student-scoped.
     *
     * `unique(examination_id, student_id)` is the hard DB-level guarantee
     * behind "one attempt per student per examination" (M23 spec §7) — it
     * is what actually stops two concurrent "start exam" requests from
     * both succeeding, not just an application-level check.
     *
     * `expires_at` is computed once, at `started_at + duration_minutes`,
     * and never recalculated — it is the sole server-side timing authority
     * (`docs/cbt.md` §8); nothing about the browser's own clock or a
     * client-submitted duration is ever trusted. `score`/`max_score`/
     * `percentage`/`passed` stay `null` until the attempt is marked (at
     * submission, or at server-detected expiry) — never mass-assignable,
     * written only by `App\Services\Cbt\ExamAttemptService`.
     */
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('status', 15)->default('in_progress'); // App\Enums\ExamAttemptStatus
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->boolean('auto_submitted')->default(false);

            $table->timestamps();

            $table->unique(['examination_id', 'student_id'], 'exam_attempts_one_per_student_unique');
            $table->index(['school_id', 'student_id'], 'exam_attempts_school_student_index');
            $table->index(['school_id', 'examination_id'], 'exam_attempts_school_exam_index');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
