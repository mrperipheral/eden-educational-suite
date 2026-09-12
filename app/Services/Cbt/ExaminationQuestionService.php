<?php

namespace App\Services\Cbt;

use App\Models\Examination;
use App\Models\ExaminationQuestion;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attaches/detaches questions to a `draft` {@see Examination}, snapshotting
 * a {@see Question}'s current content into a new {@see ExaminationQuestion}
 * row the moment it is attached — never re-read from the source question
 * afterwards. See `docs/cbt.md` §6.
 */
class ExaminationQuestionService
{
    /** @throws RuntimeException */
    public function attach(Examination $examination, Question $question): ExaminationQuestion
    {
        if (! $examination->status->structureEditable()) {
            throw new RuntimeException('Questions can only be added while the examination is a draft.');
        }

        if (! $question->isSelectable()) {
            throw new RuntimeException('Only an active Question Bank entry can be attached to an examination.');
        }

        return DB::transaction(function () use ($examination, $question) {
            $position = ((int) $examination->questions()->max('position')) + 1;

            $examinationQuestion = new ExaminationQuestion([
                'examination_id' => $examination->getKey(),
                'question_id' => $question->getKey(),
                'question_text' => $question->question_text,
                'marks' => $question->marks,
                'position' => $position,
            ]);
            $examinationQuestion->type = $question->type->value;
            $examinationQuestion->save();

            foreach ($question->options as $option) {
                $examinationQuestion->options()->create([
                    'option_text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                    'position' => $option->position,
                ]);
            }

            return $examinationQuestion;
        });
    }

    /** @throws RuntimeException */
    public function detach(Examination $examination, ExaminationQuestion $examinationQuestion): void
    {
        if (! $examination->status->structureEditable()) {
            throw new RuntimeException('Questions can only be removed while the examination is a draft.');
        }

        $examinationQuestion->delete();
    }
}
