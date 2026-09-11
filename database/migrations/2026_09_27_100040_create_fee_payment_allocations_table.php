<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 19 — how much of a `fee_payments` row is applied to one
     * `student_fee_charges` row. A payment may be split across several
     * charges, or left partly/wholly unallocated (an advance credit). Every
     * write goes through `App\Services\Fees\FeePaymentService` inside a DB
     * transaction with row locks, which rejects over-allocation (against
     * both the payment's own total and the charge's outstanding balance) and
     * cross-student / cross-school allocation. `created_by` is not
     * mass-assignable. Never hard-deleted — a correction voids the payment,
     * which excludes its allocations from balance calculations without
     * erasing them. School-owned (`App\Models\FeePaymentAllocation` uses
     * `BelongsToSchool`) so lookups never need to join through the payment
     * to stay tenant-safe. See `docs/fees.md`.
     */
    public function up(): void
    {
        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_fee_charge_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->timestamps();

            $table->index(['school_id', 'fee_payment_id'], 'fee_payment_allocations_school_payment_idx');
            $table->index(['school_id', 'student_fee_charge_id'], 'fee_payment_allocations_school_charge_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payment_allocations');
    }
};
