<?php

use App\Models\ExaminationQuestion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — the snapshotted options for one
     * {@see ExaminationQuestion}, copied from the source
     * `QuestionOption` rows at attach time (same rationale as
     * `examination_questions`). `is_correct` is the marking key — read
     * only server-side by `App\Services\Cbt\ExamAttemptService` when
     * scoring a submission; it is never serialised to a student-facing
     * response before their attempt is submitted, and no "review your
     * answers" screen exists in M23 to leak it afterwards either. See
     * `docs/cbt.md` §4, §6.
     */
    public function up(): void
    {
        Schema::create('examination_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_question_id')->constrained()->cascadeOnDelete();

            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['school_id', 'examination_question_id'], 'eq_options_school_eq_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_question_options');
    }
};
