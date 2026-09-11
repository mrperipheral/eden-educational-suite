<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — a result run: one class's compiled results for one term.
     * School-owned (`App\Models\ResultRun` uses `BelongsToSchool`). Scoped to
     * `(academic_session, academic_period, academic_level, level_arm)` — a run
     * never spans terms; nothing here assumes a three-term year, `period` is
     * the school's own configurable `AcademicPeriod`.
     *
     * `grading_scheme_id` / `result_weighting_scheme_id` are chosen once, in
     * draft, and drive compilation. `ranking_enabled` controls whether class
     * position is computed at all. `status` (`App\Enums\ResultRunStatus`) runs
     * `draft -> compiled -> reviewed -> approved -> published -> locked`; the
     * `*_by` / `*_at` pairs are stamped by their own lifecycle method and are
     * **not** mass-assignable.
     */
    public function up(): void
    {
        Schema::create('result_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scheme_id')->constrained()->restrictOnDelete();
            $table->foreignId('result_weighting_scheme_id')->constrained()->restrictOnDelete();

            $table->boolean('ranking_enabled')->default(true);
            $table->string('status', 15)->default('draft');   // App\Enums\ResultRunStatus

            $table->foreignId('compiled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('compiled_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();

            // One run per class per term.
            $table->unique(
                ['school_id', 'academic_session_id', 'academic_period_id', 'academic_level_id', 'level_arm_id'],
                'result_runs_class_term_unique',
            );
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_runs');
    }
};
