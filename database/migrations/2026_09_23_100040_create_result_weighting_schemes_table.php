<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — a school-configured result-weighting scheme ("Classwork
     * 30%, Test 30%, Exam 40%"). School-owned
     * (`App\Models\ResultWeightingScheme` uses `BelongsToSchool`). The
     * categories and their weights live on `result_weighting_scheme_items` —
     * nothing here assumes a fixed set of assessment categories.
     */
    public function up(): void
    {
        Schema::create('result_weighting_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_weighting_schemes');
    }
};
