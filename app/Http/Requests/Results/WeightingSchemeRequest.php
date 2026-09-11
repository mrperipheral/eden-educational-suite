<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\ResultWeightingScheme;
use Illuminate\Validation\Rule;

/**
 * Create / edit a result-weighting scheme (its category weights are managed
 * separately — see `WeightingSchemeItemRequest`). Requires `result.manage`.
 * `name` is unique within the school.
 */
class WeightingSchemeRequest extends ResultsRequest
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
                Rule::unique('result_weighting_schemes', 'name')->where('school_id', $schoolId)->ignore($schemeId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $schemeId = $this->route('scheme');
        if ($schemeId !== null && ! ResultWeightingScheme::query()->whereKey($schemeId)->exists()) {
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
