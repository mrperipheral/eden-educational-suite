<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 8 — which subjects an academic level offers. A pure link table:
     * no teacher, no timetable, no student enrolment — those are later modules.
     *
     * Carries `school_id` like every other school-owned table: it is written
     * from `TenantContext` during the sync, gives a tenant-scoped lookup index,
     * and keeps the "FKs stay within one tenant" rule verifiable. Both ends
     * (`academic_levels`, `subjects`) are themselves tenant-scoped models, and
     * the sync validates subject ids against the active school.
     */
    public function up(): void
    {
        Schema::create('level_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['academic_level_id', 'subject_id']);
            $table->index(['school_id', 'academic_level_id']);
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_subject');
    }
};
