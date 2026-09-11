<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 19 — one student's fee charge, snapshotted from a
     * `fee_structures` row (or entered manually) at creation time.
     * School-owned (`App\Models\StudentFeeCharge` uses `BelongsToSchool`)
     * *and* scoped to its student, so every query is tenant-safe even
     * without the student in the join (mirrors `enrollments`).
     *
     * `amount` is the immutable original charge; a later edit to the
     * `fee_structures` row it came from never touches this row — that is
     * the whole point of snapshotting (see `docs/fees.md`). `discount_amount`
     * / `waived_*` are **not** mass-assignable — they change only through
     * `StudentFeeCharge::applyDiscount()` / `waive()` / `unwaive()`. The
     * outstanding balance is always computed from `amount` minus
     * `discount_amount` minus allocated (non-voided) payments — never
     * stored, never trusted from a client total. Never hard-deleted.
     */
    public function up(): void
    {
        Schema::create('student_fee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);

            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('waiver_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_id'], 'student_fee_charges_school_student_idx');
            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'student_fee_charges_school_session_period_idx');
            $table->index(['school_id', 'fee_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_charges');
    }
};
