<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 8 — a subject the school teaches ("Mathematics", "Yoruba",
     * "Further Maths", "Home Economics" …). School-owned
     * (`App\Models\Subject` uses `BelongsToSchool`). No subject list is
     * hard-coded — each school builds its own.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Name and code are unique per school. `position` is a soft ordering
            // hint only (a flat list), so it is not made unique.
            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
