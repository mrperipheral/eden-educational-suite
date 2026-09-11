<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 19 — a school-configured fee category (e.g. "Tuition",
     * "Registration", "Examination"). School-owned
     * (`App\Models\FeeCategory` uses `BelongsToSchool`); `school_id` is
     * stamped from the tenant context and never user-editable.
     *
     * Nothing is hard-coded — every category is a school's own choice, fully
     * editable, mirroring `App\Models\AssessmentCategory` (M14) exactly.
     * Names and codes are unique **within a school**, never globally.
     * Categories are deactivated, not deleted, once fee structures /
     * charges reference them.
     */
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
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
        Schema::dropIfExists('fee_categories');
    }
};
