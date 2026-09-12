<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 26 — the central, immutable audit trail
     * (`App\Models\AuditLog`). See `docs/audit.md`.
     *
     * `school_id` is **nullable** — deliberately not `BelongsToSchool`: most
     * events happen inside a resolved tenant context and carry the acting
     * school, but a handful of genuine account-level security events (login,
     * logout, password reset, email verification) happen on routes that run
     * before/outside any tenant context (`routes/auth.php`,
     * `settings/password` — neither carries the `tenant` middleware), so
     * there is no school to attribute them to. `nullOnDelete` rather than
     * `cascadeOnDelete`: a school row disappearing must never silently erase
     * audit history.
     *
     * `actor_id` is nullable + `nullOnDelete` (an unauthenticated failed
     * login has no actor at all) and `actor_name` is a **snapshot** taken at
     * write time, so the entry stays readable even if the user row is later
     * changed. `auditable_type`/`auditable_id` are a lightweight polymorphic
     * reference **without** a real FK constraint (unlike every other
     * relationship in this schema) — deliberately, because the referenced
     * row may later be hard-deleted (e.g. `LearningMaterial`,
     * `ResultRun::destroy()`) and the audit entry must remain meaningful
     * regardless; `auditable_label` is a human-readable snapshot for exactly
     * that reason.
     *
     * `changes` is a redacted JSON before/after diff — `App\Services\Audit\
     * AuditRecorder` strips password/secret/token-shaped keys before this
     * ever reaches the database. No `updated_at` — audit rows are never
     * updated (see `App\Models\AuditLog`).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150)->nullable();

            $table->string('event', 60);
            $table->string('auditable_type', 150)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('auditable_label', 255)->nullable();

            $table->string('summary', 500);
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['school_id', 'created_at'], 'audit_logs_school_created_index');
            $table->index(['school_id', 'event'], 'audit_logs_school_event_index');
            $table->index(['school_id', 'actor_id'], 'audit_logs_school_actor_index');
            $table->index(['school_id', 'auditable_type', 'auditable_id'], 'audit_logs_school_auditable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
