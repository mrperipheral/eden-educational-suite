<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 24 — evolves M23's minimal `questions` table into a proper
     * reusable Question Bank: optional level/arm scoping (a question may
     * stay subject-only and reusable across every level of that subject),
     * a free-text `topic`, a fixed `difficulty` scale, and a real
     * `status` lifecycle (`App\Enums\QuestionStatus`: active/inactive/
     * archived) replacing the M23 `is_active` boolean.
     *
     * Existing rows are data-migrated (`is_active` true → `active`, false
     * → `inactive`) before the old column is dropped — no data is lost,
     * and every M23 `ExaminationQuestion` snapshot (which never reads
     * this table) is completely unaffected either way. See
     * `docs/question-bank.md`.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('academic_level_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->foreignId('level_arm_id')->nullable()->after('academic_level_id')->constrained()->nullOnDelete();
            $table->string('topic', 150)->nullable()->after('question_text');
            $table->string('difficulty', 10)->default('medium')->after('marks'); // App\Enums\QuestionDifficulty
            $table->string('status', 15)->default('active')->after('difficulty'); // App\Enums\QuestionStatus
        });

        DB::table('questions')->where('is_active', true)->update(['status' => 'active']);
        DB::table('questions')->where('is_active', false)->update(['status' => 'inactive']);

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_active']);
            $table->dropColumn('is_active');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['school_id', 'academic_level_id', 'level_arm_id'], 'questions_class_index');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('marks');
        });

        DB::table('questions')->where('status', 'active')->update(['is_active' => true]);
        DB::table('questions')->where('status', '!=', 'active')->update(['is_active' => false]);

        Schema::table('questions', function (Blueprint $table) {
            $table->index(['school_id', 'is_active']);
            $table->dropIndex('questions_class_index');
            $table->dropIndex(['school_id', 'status']);
            $table->dropIndex(['school_id', 'difficulty']);
            $table->dropConstrainedForeignId('level_arm_id');
            $table->dropConstrainedForeignId('academic_level_id');
            $table->dropColumn(['topic', 'difficulty', 'status']);
        });
    }
};
