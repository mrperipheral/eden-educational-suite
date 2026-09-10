<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 9 — the student record. School-owned (`App\Models\Student` uses
     * `BelongsToSchool`); `school_id` is stamped from the tenant context and is
     * never user-editable.
     *
     * Deliberately minimal PII: name, date of birth, an optional gender, the
     * admission details, a lifecycle `status`, and basic contact/address so the
     * school can reach someone. Nothing on identity grounds (religion,
     * ethnicity, ID numbers, medical, photo) — those are not M9's concern.
     *
     * The current class/arm is **not** stored here — it is derived from the
     * active `enrollments` row (see the enrollments migration).
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('preferred_name', 60)->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();          // App\Enums\Gender

            $table->string('admission_number', 40);
            $table->date('admitted_on')->nullable();
            $table->string('status', 15)->default('active');   // App\Enums\StudentStatus

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Student numbers are unique per school, never globally.
            $table->unique(['school_id', 'admission_number']);
            // Roll / status filtering and name-ordered listing.
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
