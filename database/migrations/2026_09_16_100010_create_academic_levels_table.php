<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 8 — an academic level / class band ("Primary 1", "JSS 1",
     * "Grade 4", "Reception" … the school defines its own). School-owned
     * (`App\Models\AcademicLevel` uses `BelongsToSchool`). Nothing about the
     * Nigerian system is hard-coded — a level is just a named, ordered,
     * toggleable row.
     */
    public function up(): void
    {
        Schema::create('academic_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Name, code and order are each unique per school.
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
            $table->unique(['school_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_levels');
    }
};
