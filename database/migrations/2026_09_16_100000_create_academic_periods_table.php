<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 8 — a division of an academic year (a "term", "semester",
     * "trimester", … the school names it). Belongs to one `academic_sessions`
     * row, and is school-owned in its own right (`App\Models\AcademicPeriod`
     * uses `BelongsToSchool`) so every query is tenant-scoped even when the
     * session is not in the join.
     *
     * No assumption about how many periods a year has — a school configures any
     * reasonable number. `position` orders them; at most one `is_current` per
     * session (enforced in the model, MySQL has no partial unique index).
     */
    public function up(): void
    {
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            // Names and order are unique within a session, not globally.
            $table->unique(['academic_session_id', 'name']);
            $table->unique(['academic_session_id', 'position']);
            // Tenant-scoped lookups ("this school's periods", "…for this session").
            $table->index(['school_id', 'academic_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
