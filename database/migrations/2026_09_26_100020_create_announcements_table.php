<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 18 — a school-scoped announcement. School-owned
     * (`App\Models\Announcement` uses `BelongsToSchool`). `audience`
     * (`App\Enums\AnnouncementAudience`) is a coarse, role-shaped target
     * (everyone / all staff / teachers / parents / students); `status`
     * (`App\Enums\AnnouncementStatus`) runs `draft -> published`, with
     * `published_at` stamped once, by `AnnouncementController::publish()` —
     * never mass-assigned. Never hard-deleted, matching the rest of the
     * app's domain records (a status is toggled, not destroyed).
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->text('body');
            $table->string('audience', 20)->default('everyone'); // App\Enums\AnnouncementAudience
            $table->string('status', 15)->default('draft');      // App\Enums\AnnouncementStatus
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status', 'published_at']);
            $table->index(['school_id', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
