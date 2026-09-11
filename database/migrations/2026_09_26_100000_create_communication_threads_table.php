<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 18 — the Communication Hub's thread/conversation: one piece
     * of school communication, optionally about a specific student and/or
     * guardian. School-owned (`App\Models\CommunicationThread` uses
     * `BelongsToSchool`). `category` (`App\Enums\CommunicationCategory`)
     * routes it; `status` (`App\Enums\CommunicationStatus`) runs
     * `open -> resolved|escalated`, reopenable back to `open`. `assigned_to`
     * defaults to the creator and drives who is notified of new activity.
     * Never hard-deleted — historical communication is retained.
     */
    public function up(): void
    {
        Schema::create('communication_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('category', 20)->default('general'); // App\Enums\CommunicationCategory
            $table->string('subject');
            $table->string('status', 15)->default('open');      // App\Enums\CommunicationStatus

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status', 'last_message_at']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'guardian_id']);
            $table->index(['school_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_threads');
    }
};
