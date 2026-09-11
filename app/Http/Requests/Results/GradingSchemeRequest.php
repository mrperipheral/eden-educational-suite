<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\GradingScheme;
use Illuminate\Validation\Rule;

/**
 * Create / edit a grading scheme (the bands themselves are managed separately
 * — see `GradingSchemeGradeRequest`). Requires `result.manage`. `name` is
 * unique within the school.
 */
class GradingSchemeRequest extends ResultsRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::ResultManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();
        $schemeId = $this->route('scheme');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('grading_schemes', 'name')->where('school_id', $schoolId)->ignore($schemeId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $schemeId = $this->route('scheme');
        if ($schemeId !== null && ! GradingScheme::query()->whereKey($schemeId)->exists()) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = [
            'name' => $this->string('name')->trim()->value(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
        ];

        if ($this->isMethod('patch')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
