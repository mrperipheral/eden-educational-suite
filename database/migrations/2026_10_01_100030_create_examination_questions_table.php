<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestone 23 — one question **snapshot** attached to an examination
     * (`App\Models\ExaminationQuestion`). `question_text`/`type`/`marks`
     * are copied from the source `Question` the moment it is attached —
     * never re-read from it afterwards — so a later edit to (or deletion
     * of) the source question never changes an exam that already uses it.
     * `question_id` is kept only as a soft traceability pointer
     * (`nullOnDelete`) — deleting the source question leaves this row's own
     * snapshot completely intact. See `docs/cbt.md` §6.
     *
     * Rows are only ever added/removed while the parent `Examination` is
     * `draft` (`ExaminationStatus::structureEditable()`) — enforced in
     * `App\Http\Controllers\Cbt\ExaminationQuestionController`, not here.
     */
    public function up(): void
    {
        Schema::create('examination_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();

            $table->text('question_text');
            $table->string('type', 20); // App\Enums\ExaminationQuestionType
            $table->decimal('marks', 6, 2);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['school_id', 'examination_id'], 'examination_questions_school_exam_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_questions');
    }
};
