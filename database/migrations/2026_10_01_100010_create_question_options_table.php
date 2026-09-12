<?php

use App\Models\Question;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — one selectable option for a {@see Question}.
     * Multiple-choice and true/false questions share this same table —
     * true/false is simply a question constrained to exactly two option
     * rows ("True"/"False") — so nothing downstream (validation, scoring)
     * special-cases the question type. "Exactly one correct option" is
     * enforced in `App\Http\Requests\Cbt\QuestionRequest`, not a DB
     * constraint (mirrors `grading_scheme_grades`' non-overlap rule — an
     * application-layer invariant, not something a CHECK constraint
     * expresses cleanly across MySQL/SQLite).
     */
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['school_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
