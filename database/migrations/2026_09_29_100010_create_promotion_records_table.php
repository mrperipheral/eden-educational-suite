<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 21 — one student's outcome within a `promotion_batches` run
     * — the promotion history. School-owned
     * (`App\Models\PromotionRecord` uses `BelongsToSchool`) *and*
     * student-scoped.
     *
     * `source_enrollment_id` is nullable + `nullOnDelete` — null only when
     * `status` is `failed` for a reason that never resolved a valid source
     * placement at all (e.g. the student turned out not to be eligible);
     * `target_enrollment_id` is nullable + `nullOnDelete` — null whenever
     * `status` is `skipped`/`failed` (nothing was created). `status`
     * (`App\Enums\PromotionRecordStatus`) and every FK
     * here are not mass-assignable — written only by
     * `App\Services\Promotion\PromotionService`, never from request input.
     *
     * `unique(school_id, promotion_batch_id, student_id)` — a student
     * appears at most once per batch, so a batch can never log the same
     * transition twice. See `docs/promotion.md`.
     */
    public function up(): void
    {
        Schema::create('promotion_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('target_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();

            $table->string('status', 20); // App\Enums\PromotionRecordStatus
            $table->string('failure_reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'promotion_batch_id', 'student_id'], 'promotion_records_batch_student_unique');
            $table->index(['school_id', 'student_id'], 'promotion_records_school_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_records');
    }
};
