<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 13 — one student's mark within an attendance register.
     * School-owned (`App\Models\AttendanceRecord` uses `BelongsToSchool`) *and*
     * scoped to its register, so every query is tenant-safe without a join.
     *
     * `attendance_register_id` is set from the parent relation on create and
     * never changes; `school_id` is stamped from the tenant context. `status`
     * (`App\Enums\AttendanceStatus`) is **nullable** — a null means "not yet
     * marked", and a register cannot be submitted while any record is null, so an
     * unmarked student is never counted as present. `recorded_at` / `recorded_by`
     * capture who last set the mark, enough for future auditing.
     *
     * `unique(attendance_register_id, student_id)` — a student appears once per
     * register. Attendance is historical: a record is never deleted because a
     * student later becomes inactive, withdraws or changes class.
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('status', 15)->nullable();   // App\Enums\AttendanceStatus — null = unmarked
            $table->string('note', 255)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // No duplicate student in a register.
            $table->unique(['attendance_register_id', 'student_id']);
            // "this student's attendance rows" (per-student history — the join to
            // the register carries the date).
            $table->index(['school_id', 'student_id']);
            // Register summary / status filter.
            $table->index(['school_id', 'attendance_register_id', 'status'], 'attendance_records_register_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
