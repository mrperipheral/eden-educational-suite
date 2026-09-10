<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One settings row per school (1:1). School-owned (`App\Models\SchoolSetting`
     * uses `BelongsToSchool`); the unique `school_id` is both the tenant key and
     * the 1:1 constraint. Deliberately small — the full School Settings milestone
     * adds columns here rather than reshaping.
     *
     * `completed_at` records that an administrator has reviewed settings at least
     * once (used by the onboarding checklist).
     */
    public function up(): void
    {
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('timezone', 64)->default('Africa/Lagos');
            $table->string('locale', 10)->default('en');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
