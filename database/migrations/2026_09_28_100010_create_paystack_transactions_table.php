<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 20 — one online-payment attempt through Paystack. School-owned
     * (`App\Models\PaystackTransaction` uses `BelongsToSchool`) *and* scoped
     * to its student. This is deliberately **separate** from M19's
     * `fee_payments` — an attempt can fail or be abandoned and must never
     * become an authoritative payment; only a verified `successful` row ever
     * gets a `fee_payment_id` (see `docs/paystack.md`).
     *
     * `reference` is the value **we** generate and send to Paystack as its
     * own transaction reference — globally unique (not just per school),
     * since it doubles as the lookup key for webhook delivery, which arrives
     * with no tenant context at all. `fee_payment_id` is nullable + unique:
     * set exactly once, by `App\Services\Paystack\PaymentVerificationService`,
     * the moment (and only the moment) a transaction is confirmed
     * `successful` — its presence *is* the idempotency guard against a
     * duplicate webhook/callback ever creating a second payment.
     *
     * `status` (`App\Enums\PaystackTransactionStatus`) is not mass-assignable
     * — it changes only through the model's own `markSuccessful()` /
     * `markFailed()` / `markAbandoned()` / `markVerificationFailed()`, each
     * called from inside a row-locked DB transaction.
     */
    public function up(): void
    {
        Schema::create('paystack_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('fee_payment_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('reference', 60)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending'); // App\Enums\PaystackTransactionStatus

            $table->string('authorization_url', 255)->nullable();
            $table->string('access_code', 100)->nullable();
            $table->string('provider_transaction_id', 50)->nullable();
            $table->string('gateway_response', 255)->nullable();
            $table->string('channel', 30)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'student_id'], 'paystack_transactions_school_student_idx');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_transactions');
    }
};
