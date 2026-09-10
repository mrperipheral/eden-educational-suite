<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 14 — a configurable assessment category (e.g. "Classwork",
     * "Test", "Exam"). School-owned (`App\Models\AssessmentCategory` uses
     * `BelongsToSchool`); `school_id` is stamped from the tenant context and is
     * never user-editable.
     *
     * Nothing is hard-coded — CA / Test / Exam / Assignment / Midterm are just
     * seeded examples every school can rename, reorder, deactivate or replace.
     * Names and codes are unique **within a school**, never globally.
     */
    public function up(): void
    {
        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_categories');
    }
};
