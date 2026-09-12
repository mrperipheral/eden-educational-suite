<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 20 — per-school Paystack configuration, additive onto M6's
     * `school_settings` table (that migration is untouched). A school opts
     * into online fee payment explicitly (`paystack_enabled`, default
     * `false` — M19's fee statement works with it entirely off).
     *
     * `paystack_secret_key` is `encrypted` at the model-cast level (Laravel's
     * native `Crypt` facade, keyed by `APP_KEY` — no new infrastructure) and
     * stored as `text` to hold ciphertext; it is never rendered back into a
     * form, never logged, never sent to the browser. `paystack_public_key`
     * is not secret — Paystack's own checkout page needs it client-side in a
     * future inline-JS integration, though M20's redirect flow does not
     * actually emit it to the browser either. `paystack_test_mode` is purely
     * informational (Paystack itself determines live/test by which key
     * prefix — `pk_test_…`/`sk_test_…` vs `pk_live_…`/`sk_live_…` — is
     * stored) and drives a UI badge only.
     */
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->boolean('paystack_enabled')->default(false)->after('academic_year_start_month');
            $table->string('paystack_public_key', 100)->nullable()->after('paystack_enabled');
            $table->text('paystack_secret_key')->nullable()->after('paystack_public_key');
            $table->boolean('paystack_test_mode')->default(true)->after('paystack_secret_key');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn(['paystack_enabled', 'paystack_public_key', 'paystack_secret_key', 'paystack_test_mode']);
        });
    }
};
