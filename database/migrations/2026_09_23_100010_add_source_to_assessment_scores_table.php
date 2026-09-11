<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 15 — additive column on the M14 `assessment_scores` table.
     * `source` (`App\Enums\ScoreSource`) defaults to `manual` — every score M14
     * has ever written, and every one written through the unchanged M14 entry
     * screen, is a manual (paper/offline) mark. `online_cbt` / `imported` are
     * reserved: a future CBT engine or bulk-import tool writes into this same
     * column and this same table — the M15 result engine never needs to care
     * where a score came from.
     */
    public function up(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->string('source', 15)->default('manual')->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_scores', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
