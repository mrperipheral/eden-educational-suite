<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\StudentResult;
use App\Support\Results\ResultAuthorizer;

/**
 * Save the class-teacher / principal comment on one {@see StudentResult}.
 * Requires `result.enter`, class-scoped exactly like M13/M14 (a Teacher may
 * only comment on a class they hold an active M11 assignment for;
 * `result.manage` holders may comment on any class). The **principal**
 * comment field is writable only by a `result.manage` holder — a Teacher's
 * `class_teacher_comment` is their own, but they do not speak for the
 * principal.
 */
class ResultCommentRequest extends ResultsRequest
{
    private ?StudentResult $studentResult = null;

    public function authorize(): bool
    {
        $studentResult = $this->studentResult();

        if ($studentResult === null) {
            return false;
        }

        return app(ResultAuthorizer::class)->canCommentOnRun($this->user(), $studentResult->resultRun);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_teacher_comment' => ['nullable', 'string', 'max:1000'],
            'principal_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $runId = $this->route('run');
        $studentResultId = $this->route('student_result');

        $studentResult = StudentResult::query()->where('result_run_id', $runId)->find($studentResultId);
        abort_if($studentResult === null, 404);

        $this->studentResult = $studentResult;
    }

    /**
     * Only the fields this user is actually allowed to set.
     *
     * @return array<string, string|null>
     */
    public function payload(): array
    {
        $data = ['class_teacher_comment' => $this->filled('class_teacher_comment') ? trim((string) $this->input('class_teacher_comment')) : null];

        if ($this->user()->hasPermission(Permission::ResultManage)) {
            $data['principal_comment'] = $this->filled('principal_comment') ? trim((string) $this->input('principal_comment')) : null;
        }

        return $data;
    }

    public function studentResult(): ?StudentResult
    {
        return $this->studentResult;
    }
}
