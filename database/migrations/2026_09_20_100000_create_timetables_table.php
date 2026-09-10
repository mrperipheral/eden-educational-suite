<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 12 — a timetable / weekly schedule. School-owned
     * (`App\Models\Timetable` uses `BelongsToSchool`); `school_id` is stamped
     * from the tenant context and is never user-editable.
     *
     * A timetable is scoped to exactly one academic session (and optionally one
     * period / term). Its lessons ({@see timetable_entries}) inherit that scope,
     * so a timetable's entries can never cross academic sessions. Nothing here
     * assumes a number of terms, days or periods.
     *
     * `status` (`App\Enums\TimetableStatus`) is `draft` or `published`; a school
     * may keep several timetables per session (e.g. an old published one plus a
     * new draft) so history is preserved. Publishing is refused while any
     * scheduling conflict exists.
     */
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 120);
            $table->string('status', 15)->default('draft');   // App\Enums\TimetableStatus
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // "timetables for this session / term".
            $table->index(['school_id', 'academic_session_id', 'academic_period_id'], 'timetables_scope_index');
            // "the school's published timetables".
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
