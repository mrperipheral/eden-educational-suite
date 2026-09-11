<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 19 — a manually recorded payment (cash / bank transfer /
     * POS / cheque / other — never Paystack; that is M20). School-owned
     * (`App\Models\FeePayment` uses `BelongsToSchool`) *and* scoped to its
     * student.
     *
     * `reference` is unique **within a school** — the duplicate-reference
     * guard the spec requires. `recorded_by` is not mass-assignable (set
     * from the authenticated user). A payment is never edited or deleted
     * once made — a correction goes through `FeePayment::void()`
     * (`voided_at` / `voided_by` / `void_reason`), which excludes it from
     * balance calculations without erasing the historical row. See
     * `docs/fees.md`.
     */
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->string('reference', 60);
            $table->string('method', 20); // App\Enums\PaymentMethod
            $table->string('payer_name', 150)->nullable();
            $table->string('payer_phone', 30)->nullable();
            $table->string('payer_email', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'student_id'], 'fee_payments_school_student_idx');
            $table->index(['school_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
