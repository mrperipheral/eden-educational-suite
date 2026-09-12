<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 21 — graduation metadata, additive onto M9's `students`
     * table (that migration is untouched). A student can only ever be
     * graduated once at a time, so this is a handful of columns on the row
     * itself rather than a separate history table — `App\Enums\StudentStatus::
     * Graduated` already exists (M9); this just records *when*, *for which
     * session*, *by whom* and *why* a transition to it happened. Reversible
     * (`App\Services\Promotion\GraduationService::reactivate()`), which
     * simply clears these columns and reverts `status` — never a second,
     * competing lifecycle field. See `docs/promotion.md`.
     *
     * None of these are mass-assignable — written only by
     * `GraduationService`, mirroring how `status` itself already isn't.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('graduated_at')->nullable()->after('status');
            $table->foreignId('graduated_academic_session_id')->nullable()->after('graduated_at')
                ->constrained('academic_sessions')->nullOnDelete();
            $table->string('graduation_notes', 255)->nullable()->after('graduated_academic_session_id');
            $table->foreignId('graduated_by')->nullable()->after('graduation_notes')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('graduated_academic_session_id');
            $table->dropConstrainedForeignId('graduated_by');
            $table->dropColumn(['graduated_at', 'graduation_notes']);
        });
    }
};
