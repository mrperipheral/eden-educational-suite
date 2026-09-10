<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 14 — one student's score in an {@see assessments} row.
     * School-owned (`App\Models\AssessmentScore` uses `BelongsToSchool`) *and*
     * scoped to its assessment, so every query is tenant-safe without a join.
     *
     * `score` is **nullable** — a null means "not entered yet". A valid score is
     * `0 <= score <= assessment.max_score` with at most 2 decimal places; the
     * bounds are enforced in `App\Http\Requests\Assessment\ScoreRequest`, not by
     * a DB constraint (the maximum lives on the parent). `recorded_at` /
     * `recorded_by` capture who last set it.
     *
     * `unique(assessment_id, student_id)` — a student appears once per
     * assessment. Scores are historical: a row is never deleted because a
     * student later becomes inactive, withdraws or changes class.
     *
     * No grade / percentage / rank is stored — that is M15's to derive.
     */
    public function up(): void
    {
        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->decimal('score', 6, 2)->nullable();   // null = not entered
            $table->string('comment', 500)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'assessment_id'], 'assessment_scores_tenant_assessment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
    }
};
