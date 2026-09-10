<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 11 — the teacher professional record. School-owned
     * (`App\Models\Teacher` uses `BelongsToSchool`); `school_id` is stamped from
     * the tenant context and is never user-editable.
     *
     * Deliberately minimal professional data: a name, an employee number, the
     * ways to reach them (email, phone), a start date, a lifecycle `status`, an
     * optional address and free-text notes. Nothing on identity grounds — no
     * NIN / BVN / government ID, no financial or medical data, no authentication
     * credentials. HR / payroll is out of scope.
     *
     * `user_id` is a **nullable** link to an existing application account — a
     * teacher is a professional record first; whether they can sign in is a
     * separate, later concern (there is no invitation / credential flow in M11).
     * The link is unique per school.
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('preferred_name', 60)->nullable();

            $table->string('employee_number', 40);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('employed_on')->nullable();
            $table->string('status', 15)->default('active');   // App\Enums\TeacherStatus

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Employee numbers are unique per school, never globally.
            $table->unique(['school_id', 'employee_number']);
            // One teacher record per linked account per school (many NULLs allowed).
            $table->unique(['school_id', 'user_id']);
            // Status filtering and name-ordered listing.
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
