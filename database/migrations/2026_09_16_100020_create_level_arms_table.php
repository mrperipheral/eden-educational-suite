<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 8 — an arm / stream / division within an academic level
     * ("Gold", "A", "Science", "Blue" …). Belongs to one `academic_levels` row
     * and is school-owned in its own right (`App\Models\LevelArm` uses
     * `BelongsToSchool`).
     */
    public function up(): void
    {
        Schema::create('level_arms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('code', 20);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Name, code and order are unique within a level, not globally.
            $table->unique(['academic_level_id', 'name']);
            $table->unique(['academic_level_id', 'code']);
            $table->unique(['academic_level_id', 'position']);
            $table->index(['school_id', 'academic_level_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_arms');
    }
};
