<?php

namespace App\Http\Requests\Assessment;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Support\Assessment\AssessmentAuthorizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

/**
 * Save completion records for an assignment
 * (`PATCH /assessments/assignments/{assignment}/submissions`).
 *
 * Allowed while the assignment is not closed, and only for a user who may record
 * for its class + subject. The `submissions` map is keyed by student id; every
 * key must be on this assignment's roster.
 */
class SubmissionRequest extends AssessmentModuleRequest
{
    private ?Assignment $assignment = null;

    public function authorize(): bool
    {
        $assignment = $this->assignment();

        if ($assignment === null || ! $assignment->tracksCompletion()) {
            return false;
        }

        return app(AssessmentAuthorizer::class)->canRecordFor(
            $this->user(),
            $assignment->academic_level_id,
            $assignment->level_arm_id,
            $assignment->subject_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'submissions' => ['required', 'array'],
            'submissions.*.status' => ['required', new Enum(AssignmentSubmissionStatus::class)],
            'submissions.*.submitted_on' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:today'],
            'submissions.*.remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rosterIds = $this->assignment()->submissions()->pluck('student_id')->all();

            foreach (array_keys((array) $this->input('submissions', [])) as $studentId) {
                if (! in_array((int) $studentId, $rosterIds, true)) {
                    $validator->errors()->add('submissions', __('One or more students are not part of this assignment.'));

                    return;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->assignment() === null) {
            abort(404);
        }
    }

    /**
     * @return array<int, array{status: string, submitted_on: string|null, remark: string|null}>
     */
    public function submissions(): array
    {
        $out = [];

        foreach ((array) $this->validated('submissions') as $studentId => $row) {
            $submittedOn = $row['submitted_on'] ?? '';
            $remark = $row['remark'] ?? '';

            $out[(int) $studentId] = [
                'status' => $row['status'],
                'submitted_on' => $submittedOn !== '' ? $submittedOn : null,
                'remark' => trim((string) $remark) !== '' ? trim((string) $remark) : null,
            ];
        }

        return $out;
    }

    public function assignment(): ?Assignment
    {
        return $this->assignment ??= Assignment::query()->find($this->route('assignment'));
    }
}
