<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 21 — one bulk promotion run: "these students move from this
     * source session/level/arm to this target session/level/arm."
     * School-owned (`App\Models\PromotionBatch` uses `BelongsToSchool`).
     *
     * `status` (`App\Enums\PromotionBatchStatus`) is computed once, after
     * every selected student has been processed by
     * `App\Services\Promotion\PromotionService` — not mass-assignable.
     * `source_academic_period_id` / `source_level_arm_id` /
     * `target_level_arm_id` are nullable — a school need not use terms or
     * arms/streams. See `docs/promotion.md`.
     */
    public function up(): void
    {
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->foreignId('source_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('source_academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->foreignId('source_academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->foreignId('source_level_arm_id')->nullable()->constrained('level_arms')->nullOnDelete();

            $table->foreignId('target_academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('target_academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->foreignId('target_level_arm_id')->nullable()->constrained('level_arms')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('completed'); // App\Enums\PromotionBatchStatus
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'target_academic_session_id'], 'promotion_batches_school_target_session_idx');
            $table->index(['school_id', 'source_academic_session_id'], 'promotion_batches_school_source_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_batches');
    }
};
