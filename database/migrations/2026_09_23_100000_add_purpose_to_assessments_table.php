<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — additive column on the M14 `assessments` table (not a
     * rewrite of M14). `purpose` (`App\Enums\AssessmentPurpose`) defaults to
     * `academic`, so every assessment created before this migration — and every
     * one created through the unchanged M14 UI — is automatically eligible for
     * result compilation. `practice` / `entry_placement` are reserved for a
     * later milestone; M15's result compiler only ever considers `academic`
     * assessments, so a future entry test can never leak into a termly result.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('purpose', 20)->default('academic')->after('assignment_id');
            $table->index(['school_id', 'purpose'], 'assessments_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex('assessments_purpose_index');
            $table->dropColumn('purpose');
        });
    }
};
