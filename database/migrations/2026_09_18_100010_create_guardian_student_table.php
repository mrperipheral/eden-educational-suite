<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 10 — the student ↔ guardian link. School-owned
     * (`App\Models\GuardianStudent` uses `BelongsToSchool`) *and* it carries both
     * `student_id` and `guardian_id`, so every query is tenant-safe without a
     * parent in the join.
     *
     * A student may have several guardians and a guardian several students
     * (within the one school). Each link records the `relationship`
     * (`App\Enums\GuardianRelationship`) and whether this guardian is the
     * student's `is_primary` contact. At most one primary per student — enforced
     * in `GuardianStudent::makePrimary()`. A `(student_id, guardian_id)` pair is
     * unique, so the same guardian cannot be linked to the same student twice.
     */
    public function up(): void
    {
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();

            $table->string('relationship', 20);   // App\Enums\GuardianRelationship
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // No duplicate link between the same student and guardian.
            $table->unique(['student_id', 'guardian_id']);
            // "guardians for this student" (+ the primary lookup).
            $table->index(['school_id', 'student_id', 'is_primary']);
            // "students for this guardian".
            $table->index(['school_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
    }
};
