<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — the category-by-category breakdown of one
     * {@see student_subject_results} row (`App\Models\StudentSubjectResultComponent`)
     * — the "CA1 8/10, CA2 9/10, Exam 55/70" line items a report card shows.
     * School-owned **+** scoped to its subject result. One row per weighting
     * scheme item that contributed.
     *
     * `category_name_snapshot` / `weight_percentage_snapshot` freeze the
     * category's label and weight *as compiled* — a later rename of the
     * category or edit of the weighting scheme never rewrites a historical
     * component row. `raw_score` / `raw_max_score` are this category's
     * (possibly averaged, if more than one locked assessment) score out of its
     * maximum; `score_percentage` is that as a percentage;
     * `weighted_contribution` is `score_percentage * weight / 100` — the
     * figure that sums (across a subject's components) to the parent's
     * `percentage`.
     */
    public function up(): void
    {
        Schema::create('student_subject_result_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_subject_result_id')
                ->constrained(indexName: 'ssr_components_result_fk')
                ->cascadeOnDelete();
            $table->foreignId('assessment_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('category_name_snapshot');
            $table->decimal('weight_percentage_snapshot', 5, 2);
            $table->decimal('raw_score', 6, 2)->nullable();
            $table->decimal('raw_max_score', 6, 2)->nullable();
            $table->decimal('score_percentage', 5, 2)->nullable();
            $table->decimal('weighted_contribution', 5, 2)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['school_id', 'student_subject_result_id'], 'ssr_components_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_result_components');
    }
};
