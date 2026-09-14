<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M29.5 — school portal branding: an accent colour, a cover/background image,
 * and an optional motto/tagline, alongside the existing `logo_path` and
 * `brand_color`. Additive only; every new column is nullable so existing rows
 * (and the existing branding feature) are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->char('accent_color', 7)->nullable()->after('brand_color');
            $table->string('cover_image_path')->nullable()->after('accent_color');
            $table->string('motto', 160)->nullable()->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn(['accent_color', 'cover_image_path', 'motto']);
        });
    }
};
