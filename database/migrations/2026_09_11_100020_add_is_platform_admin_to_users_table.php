<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-owner flag. This is the ONLY authorization primitive introduced
     * in this milestone: it is a *platform* capability, entirely separate from
     * any (future) per-school role. A platform admin can manage schools and can
     * enter any school's tenant context; they still operate on school data
     * through that context, not around it.
     *
     * Not indexed: extremely low cardinality, queried rarely, and MySQL has no
     * partial indexes. Not mass-assignable (kept out of User::$fillable).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
