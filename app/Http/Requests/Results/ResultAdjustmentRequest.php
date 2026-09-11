<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\ResultAdjustment;
use App\Models\StudentSubjectResult;
use Illuminate\Contracts\Validation\Validator;

/**
 * Propose a {@see ResultAdjustment} on one
 * {@see StudentSubjectResult}. Requires `result.adjust`, and only once the
 * parent run requires the adjustment workflow (approved / published /
 * locked) — before that, the correct fix is simply to recompile. This only
 * *proposes* a new percentage; nothing changes until an explicit "apply".
 */
class ResultAdjustmentRequest extends ResultsRequest
{
    private ?StudentSubjectResult $subjectResult = null;

    public function authorize(): bool
    {
        $subjectResult = $this->subjectResult();

        if ($subjectResult === null || ! $subjectResult->resultRun->requiresAdjustment()) {
            return false;
        }

        return $this->user()?->hasPermission(Permission::ResultAdjust) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'adjusted_value' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((float) $this->input('adjusted_value') === (float) $this->subjectResult()->percentage) {
                $validator->errors()->add('adjusted_value', __('The adjusted value must be different from the current percentage.'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $subjectResult = StudentSubjectResult::query()
            ->where('result_run_id', $this->route('run'))
            ->find($this->route('subject_result'));

        abort_if($subjectResult === null, 404);

        $this->subjectResult = $subjectResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'field' => 'percentage',
            'original_value' => $this->subjectResult()->percentage,
            'adjusted_value' => $this->input('adjusted_value'),
            'reason' => $this->string('reason')->trim()->value(),
        ];
    }

    public function subjectResult(): ?StudentSubjectResult
    {
        return $this->subjectResult;
    }
}
