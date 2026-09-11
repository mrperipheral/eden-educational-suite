<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 17 — links a student record to an existing application
     * account so its owner can sign in to the Student Portal. Additive onto
     * M9's `students` table (that migration is untouched) — mirrors
     * `guardians.user_id` (M16) / `teachers.user_id` (M11) exactly:
     * **nullable**, set only through a dedicated endpoint, never
     * mass-assigned. A student record is a person first; whether they can
     * sign in is a separate, later concern.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('school_id')->constrained()->nullOnDelete();

            // One student record per linked account per school (many NULLs allowed).
            $table->unique(['school_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
