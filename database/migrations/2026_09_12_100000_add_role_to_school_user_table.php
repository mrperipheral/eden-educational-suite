<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-school role on the membership pivot.
     *
     * Nullable: a membership can exist without a role (just added, role pending),
     * in which case the user has *no* permissions in that school. One role per
     * (user, school) — multi-role-per-school is a documented future extension.
     *
     * Indexed as `(school_id, role)` for the "members with role X in school Y"
     * query behind the Members screen.
     */
    public function up(): void
    {
        Schema::table('school_user', function (Blueprint $table) {
            $table->string('role', 30)->nullable()->after('user_id');
            $table->index(['school_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('school_user', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
