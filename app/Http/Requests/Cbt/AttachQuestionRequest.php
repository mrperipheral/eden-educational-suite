<?php

namespace App\Http\Requests\Cbt;

use App\Enums\Permission;
use App\Models\Examination;
use App\Models\Question;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Attach an existing {@see Question} to a `draft`
 * {@see Examination}. The coarse gate is holding either
 * `cbt.author` or `cbt.manage`; the controller re-checks the exam's own
 * class/subject via `App\Support\Cbt\CbtAuthorizer`, and the exam's own
 * `structureEditable()` state, before calling
 * `App\Services\Cbt\ExaminationQuestionService::attach()`.
 */
class AttachQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CbtAuthor)
            || $this->user()?->hasPermission(Permission::CbtManage)
            ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();

        return [
            'question_id' => [
                'required', 'integer',
                Rule::exists('questions', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
