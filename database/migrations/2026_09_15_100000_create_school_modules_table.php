<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 7 — per-school feature/module activation.
     *
     * One row per school *per module the school has an explicit preference for*.
     * Absence of a row means "use the catalogue default"
     * (`App\Enums\Module::enabledByDefault()`), so a freshly onboarded school
     * needs no rows at all. School-owned (`App\Models\SchoolModule` uses
     * `BelongsToSchool`).
     *
     * The `(school_id, module)` unique key is both the 1-per-module constraint
     * and the lookup index — every read is "all rows for the active school", and
     * `school_id` leads the key.
     */
    public function up(): void
    {
        Schema::create('school_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['school_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_modules');
    }
};
