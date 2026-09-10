<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 10 — the guardian / parent record. School-owned
     * (`App\Models\Guardian` uses `BelongsToSchool`); `school_id` is stamped from
     * the tenant context and is never user-editable.
     *
     * Deliberately minimal contact data: a name, the ways to reach them (phone,
     * an alternate phone, email), a postal address, and free-text notes. Nothing
     * on identity grounds — no government ID / BVN / NIN, no financial, medical
     * or emergency data. Portal sign-in is a separate, later concern; there are
     * no credentials here.
     */
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('preferred_name', 60)->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('alt_phone', 40)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Name-ordered listing and name search.
            $table->index(['school_id', 'last_name', 'first_name']);
            // Phone / email lookup from the search box.
            $table->index(['school_id', 'phone']);
            $table->index(['school_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
