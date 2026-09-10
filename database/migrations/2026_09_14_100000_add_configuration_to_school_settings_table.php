<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 6 — expand the M5 `school_settings` foundation into a full
     * school-configuration record. Typed columns (not a JSON blob) so each
     * value is validated, defaulted and queryable. All school-owned via
     * `BelongsToSchool`; no extra index needed (1:1 on the unique `school_id`).
     *
     * `logo_path` is written only by the branding upload handler — it is kept
     * out of `$fillable`, like `completed_at` and `school_id`.
     */
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            // ---- Profile: address / location ------------------------------
            $table->string('address_line1')->nullable()->after('contact_phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 120)->nullable()->after('address_line2');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->char('country', 2)->nullable()->default('NG')->after('postal_code');
            $table->string('website_url')->nullable()->after('country');

            // ---- Branding ------------------------------------------------
            $table->string('logo_path')->nullable()->after('website_url');   // guarded
            $table->char('brand_color', 7)->nullable()->after('logo_path');   // #RRGGBB

            // ---- Regional / formatting ---------------------------------
            $table->char('currency', 3)->default('NGN')->after('locale');
            $table->string('date_format', 20)->default('d/m/Y')->after('currency');
            $table->unsignedTinyInteger('week_starts_on')->default(1)->after('date_format'); // 0=Sun..6=Sat

            // ---- Academic calendar boundary (Academic Management reads this) --
            $table->unsignedTinyInteger('academic_year_start_month')->default(9)->after('week_starts_on'); // 1..12
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'address_line1', 'address_line2', 'city', 'state', 'postal_code',
                'country', 'website_url', 'logo_path', 'brand_color',
                'currency', 'date_format', 'week_starts_on', 'academic_year_start_month',
            ]);
        });
    }
};
