<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A school's academic year container ("2025/2026"). School-owned
     * (`App\Models\AcademicSession` uses `BelongsToSchool`).
     *
     * Deliberately structure-agnostic: no terms, no Nigerian assumptions — the
     * Academic Management milestone builds the calendar / term model on top of
     * this. Onboarding only needs the first session to exist.
     *
     * At most one `is_current` session per school (enforced in the model, since
     * MySQL has no partial unique index).
     */
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_sessions');
    }
};
