<?php

use App\Models\ExamAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — one question's answer within one
     * {@see ExamAttempt} (`App\Models\ExamAnswer`). Every row
     * for an attempt is bulk-inserted (`selected_option_id` null,
     * unanswered) the moment the attempt starts — one per
     * `ExaminationQuestion` — mirroring M13's own `AttendanceRecord`
     * roster-snapshot convention, so "answered vs unanswered" is always a
     * plain `whereNull('selected_option_id')` check, never a derived
     * "missing row" case. Answering a question **updates** its existing
     * row; nothing here is ever mass-assigned from request input beyond
     * `selected_option_id` itself, and that write is only ever accepted
     * while the parent attempt is still `in_progress` and unexpired
     * (`App\Services\Cbt\ExamAttemptService`).
     *
     * `is_correct`/`marks_awarded` stay `null` until marking time — the
     * server compares `selected_option_id` against the snapshotted
     * `ExaminationQuestionOption.is_correct`, never trusting a
     * client-submitted correctness/marks value.
     */
    public function up(): void
    {
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('examination_question_options')->nullOnDelete();

            $table->dateTime('answered_at')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 6, 2)->nullable();

            $table->timestamps();

            $table->unique(['exam_attempt_id', 'examination_question_id'], 'exam_answers_one_per_question_unique');
            $table->index(['school_id', 'exam_attempt_id'], 'exam_answers_school_attempt_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
    }
};
