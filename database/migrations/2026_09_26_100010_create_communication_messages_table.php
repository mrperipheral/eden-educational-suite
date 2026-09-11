<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 18 — one message within a `communication_threads` thread.
     * School-owned (`App\Models\CommunicationMessage` uses `BelongsToSchool`)
     * with its own `school_id`, kept alongside `communication_thread_id` so
     * lookups never need to join through the thread to stay tenant-safe.
     * Every message is authored by a staff `User` in this milestone — the
     * Communication Hub is staff-facing; see `docs/communication.md`. Never
     * hard-deleted.
     */
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('communication_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['school_id', 'communication_thread_id', 'created_at'], 'communication_messages_school_thread_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
