<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 12 — one scheduled lesson within a timetable. School-owned
     * (`App\Models\TimetableEntry` uses `BelongsToSchool`) *and* scoped to its
     * timetable, so every query — overlap checks included — is tenant-safe
     * without a join.
     *
     * A lesson identifies the actual class receiving it (`level_arm_id`, always
     * set — timetabling happens at the arm/class level, not merely the level),
     * the subject, the teacher, a `weekday` (`App\Enums\Weekday`, 0 = Sunday …
     * 6 = Saturday — no Monday–Friday assumption), a `start_time` / `end_time`
     * (`HH:MM` strings, treated as half-open `[start, end)` so back-to-back
     * lessons do not clash) and an optional free-text `room`.
     *
     * The session / period come from the parent {@see timetables} row. Scheduling
     * rules (no teacher / class / room double-booking, subject offered by the
     * level, an active teacher assignment backing the pairing) are enforced in
     * `App\Http\Requests\Timetable\TimetableEntryRequest`. `room` is deliberately
     * plain text — M12 is not a facilities-management module.
     */
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();

            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('weekday');   // App\Enums\Weekday (Carbon numbering)
            $table->string('start_time', 5);          // "HH:MM"
            $table->string('end_time', 5);            // "HH:MM"
            $table->string('room', 60)->nullable();

            $table->timestamps();

            // Grid render: a timetable's lessons by day and time.
            $table->index(['school_id', 'timetable_id', 'weekday', 'start_time'], 'tt_entries_grid_index');
            // Overlap detection — one narrow index per booked resource.
            $table->index(['school_id', 'timetable_id', 'teacher_id', 'weekday'], 'tt_entries_teacher_index');
            $table->index(['school_id', 'timetable_id', 'level_arm_id', 'weekday'], 'tt_entries_class_index');
            $table->index(['school_id', 'timetable_id', 'room', 'weekday'], 'tt_entries_room_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
