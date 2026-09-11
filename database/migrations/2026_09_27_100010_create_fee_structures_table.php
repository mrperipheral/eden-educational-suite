<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 19 — a fee structure: "this category costs this amount for
     * this session/period/level(/arm)". School-owned
     * (`App\Models\FeeStructure` uses `BelongsToSchool`). Freely editable
     * config, like `App\Models\GradingScheme` (M15) — editing a structure's
     * `amount` later never touches a `student_fee_charges` row already
     * created from it, because a charge snapshots its own amount (see
     * `docs/fees.md`).
     *
     * `academic_period_id` and `level_arm_id` are nullable ("optional
     * arm"/session-wide fees not tied to one term), mirroring
     * `enrollments`' own nullability for the same columns.
     */
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();

            $table->timestamps();

            $table->index(['school_id', 'academic_session_id', 'academic_level_id'], 'fee_structures_school_session_level_idx');
            $table->index(['school_id', 'is_active']);
            $table->index(['school_id', 'fee_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
