<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — one assessment-category weight within a
     * {@see result_weighting_schemes} row (`App\Models\ResultWeightingSchemeItem`).
     * School-owned **+** scoped to its scheme. A category not listed here
     * simply contributes nothing to a result compiled with this scheme —
     * `weight_percentage` across an *active* scheme's items must sum to
     * exactly 100, enforced in the Form Request when the scheme is saved.
     */
    public function up(): void
    {
        Schema::create('result_weighting_scheme_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_weighting_scheme_id')
                ->constrained(indexName: 'weighting_scheme_items_scheme_fk')
                ->cascadeOnDelete();
            $table->foreignId('assessment_category_id')->constrained()->cascadeOnDelete();

            $table->decimal('weight_percentage', 5, 2);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['result_weighting_scheme_id', 'assessment_category_id'], 'weighting_scheme_category_unique');
            $table->index(['school_id', 'result_weighting_scheme_id'], 'weighting_items_scheme_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_weighting_scheme_items');
    }
};
