<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 18 — an in-app notification for one recipient. School-owned
     * (`App\Models\Notification` uses `BelongsToSchool`), deliberately its
     * own table (`user_notifications`, not Laravel's conventional
     * `notifications` table): the framework's default `Notifiable::
     * notifications()` relation assumes a polymorphic, non-tenant-scoped
     * shape, which would bypass `SchoolScope`. `type`
     * (`App\Enums\NotificationType`) categorises it; `data` is a small,
     * extensible JSON payload for future channels/rendering; `channel`
     * records which `App\Enums\NotificationChannel` it went out on — only
     * `in_app` is ever written in this milestone. Rows are created only by
     * `App\Services\Notifications\NotificationDispatcher`, never from
     * request input.
     */
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);      // App\Enums\NotificationType
            $table->string('channel', 20)->default('in_app'); // App\Enums\NotificationChannel
            $table->string('title');
            $table->string('message');
            $table->string('url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'user_id', 'read_at']);
            $table->index(['school_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
