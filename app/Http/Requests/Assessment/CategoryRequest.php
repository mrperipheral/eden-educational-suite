<?php

namespace App\Http\Requests\Assessment;

use App\Enums\Permission;
use App\Models\AssessmentCategory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / edit an assessment category. Categories are school configuration, so
 * this needs `assessment.manage` (School Admin / Principal) — not the coarse
 * `assessment.record`. `name` and `code` are unique within the school.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::AssessmentManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(TenantContext::class)->idOrFail();
        $categoryId = $this->route('category');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('assessment_categories', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($categoryId),
            ],
            'code' => [
                'nullable', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/',
                Rule::unique('assessment_categories', 'code')
                    ->where('school_id', $schoolId)
                    ->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $categoryId = $this->route('category');
        if ($categoryId !== null && ! AssessmentCategory::query()->whereKey($categoryId)->exists()) {
            abort(404);
        }

        if ($this->has('code')) {
            $code = trim((string) $this->input('code'));
            $this->merge(['code' => $code === '' ? null : strtoupper($code)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('Use letters, numbers, spaces and hyphens only.'),
            'name.unique' => __('A category with that name already exists.'),
            'code.unique' => __('A category with that code already exists.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'name' => $this->string('name')->trim()->value(),
            'code' => $this->input('code'),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'position' => (int) ($this->input('position') ?: 0),
        ];

        // On create the DB default (`true`) applies; on edit the checkbox wins.
        if ($this->isMethod('patch')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
