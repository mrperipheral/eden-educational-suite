<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — one grade band within a {@see grading_schemes} row
     * (`App\Models\GradingSchemeGrade`). School-owned **+** scoped to its
     * scheme. Ranges (`min_percentage`..`max_percentage`, inclusive) must not
     * overlap another active band in the same scheme and must sit within
     * 0-100 — enforced in the Form Request, not a DB constraint (portable range
     * overlap checks are not expressible as a simple column constraint).
     */
    public function up(): void
    {
        Schema::create('grading_scheme_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scheme_id')->constrained()->cascadeOnDelete();

            $table->string('code', 10);
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->string('remark', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['grading_scheme_id', 'code']);
            $table->index(['school_id', 'grading_scheme_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scheme_grades');
    }
};
