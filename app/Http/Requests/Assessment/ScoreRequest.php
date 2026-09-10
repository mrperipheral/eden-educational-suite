<?php

namespace App\Http\Requests\Assessment;

use App\Models\Assessment;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;

/**
 * Save scores for an assessment (`PATCH /assessments/{assessment}/scores`).
 *
 * Allowed only while the assessment is not locked, and only for a user who may
 * record for its class + subject. The `scores` map is keyed by student id; every
 * key must be a student already on this assessment's roster (so a cross-school
 * or non-eligible student id is rejected). Each score must be `0 <= score <=
 * max_score` with at most two decimal places; a blank score clears it.
 */
class ScoreRequest extends AssessmentModuleRequest
{
    private ?Assessment $assessment = null;

    public function authorize(): bool
    {
        $assessment = $this->assessment();

        if ($assessment === null || ! $assessment->acceptsScores()) {
            return false;
        }

        return app(AssessmentAuthorizer::class)->canRecordFor(
            $this->user(),
            $assessment->academic_level_id,
            $assessment->level_arm_id,
            $assessment->subject_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'scores.*.comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assessment = $this->assessment();
            $max = (float) $assessment->max_score;
            $rosterIds = $assessment->scores()->pluck('student_id')->all();

            foreach ((array) $this->input('scores', []) as $studentId => $row) {
                if (! in_array((int) $studentId, $rosterIds, true)) {
                    $validator->errors()->add('scores', __('One or more students are not part of this assessment.'));

                    return;
                }

                $value = $row['score'] ?? '';
                if ($value !== '' && $value !== null && (float) $value > $max) {
                    $validator->errors()->add("scores.{$studentId}.score", __('The score cannot exceed the maximum of :n.', ['n' => $max]));
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->assessment() === null) {
            abort(404);
        }
    }

    /**
     * The submitted scores, keyed by int student id.
     *
     * @return array<int, array{score: string|null, comment: string|null}>
     */
    public function scores(): array
    {
        $out = [];

        foreach ((array) $this->validated('scores') as $studentId => $row) {
            $score = $row['score'] ?? '';
            $comment = $row['comment'] ?? '';

            $out[(int) $studentId] = [
                'score' => ($score === '' || $score === null) ? null : (string) $score,
                'comment' => trim((string) $comment) !== '' ? trim((string) $comment) : null,
            ];
        }

        return $out;
    }

    public function assessment(): ?Assessment
    {
        return $this->assessment ??= Assessment::query()->find($this->route('assessment'));
    }
}
