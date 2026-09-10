<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 13 — an attendance register: one class's attendance for one day.
     * School-owned (`App\Models\AttendanceRegister` uses `BelongsToSchool`);
     * `school_id` is stamped from the tenant context and is never user-editable.
     *
     * A register is tied to an academic session (+ optional period), a level and
     * a specific arm/class, and a date. It does **not** depend on the timetable —
     * a school records attendance whether or not the Timetable module is on.
     *
     * `status` (`App\Enums\AttendanceRegisterStatus`) is `draft` while marks are
     * being entered and `submitted` once locked; `submitted_at` / `submitted_by`
     * record who locked it. Nothing here assumes a term count, a set of school
     * days or a year structure.
     */
    public function up(): void
    {
        Schema::create('attendance_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_arm_id')->constrained()->cascadeOnDelete();

            $table->date('attendance_date');
            $table->string('status', 15)->default('draft');   // App\Enums\AttendanceRegisterStatus
            $table->string('notes', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One register per class per day — duplicate prevention + the main lookup.
            $table->unique(['school_id', 'level_arm_id', 'attendance_date'], 'attendance_registers_class_day_unique');
            // "registers for this date".
            $table->index(['school_id', 'attendance_date']);
            // "registers for this session / term".
            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'attendance_registers_scope_index');
            // status filter.
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_registers');
    }
};
