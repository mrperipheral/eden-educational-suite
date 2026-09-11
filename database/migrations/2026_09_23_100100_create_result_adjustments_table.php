<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — a controlled, auditable correction to one
     * {@see student_subject_results} row (`App\Models\ResultAdjustment`).
     * School-owned **+** scoped to its subject result.
     *
     * This is **not** a general edit endpoint: `result.adjust` only ever
     * *proposes* a new `percentage` here (`original_value` / `adjusted_value`
     * snapshotted, `reason` required); a second, explicit "apply" action
     * (still `result.adjust`) is what actually changes the subject result and
     * recomputes the run's ranking. `status`
     * (`App\Enums\ResultAdjustmentStatus`) is `pending` until applied or
     * rejected — a pending row has no effect. Only reachable once a run
     * requires the adjustment workflow (approved / published / locked).
     */
    public function up(): void
    {
        Schema::create('result_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_subject_result_id')->constrained()->cascadeOnDelete();

            $table->string('field', 30)->default('percentage');
            $table->decimal('original_value', 5, 2);
            $table->decimal('adjusted_value', 5, 2);
            $table->text('reason');
            $table->string('status', 15)->default('pending');   // App\Enums\ResultAdjustmentStatus

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_subject_result_id'], 'result_adjustments_parent_index');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_adjustments');
    }
};
