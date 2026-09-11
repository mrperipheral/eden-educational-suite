<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;
use App\Models\ResultRun;
use App\Models\ResultWeightingScheme;
use App\Models\ResultWeightingSchemeItem;
use Illuminate\Validation\Rule;

/**
 * Add / edit one category weight on a {@see ResultWeightingScheme}. Requires
 * `result.manage`. A category appears at most once per scheme; the scheme's
 * *total* weight is validated to equal 100 only when the scheme is actually
 * selected for a {@see ResultRun} — items may be built up
 * incrementally without artificially blocking each individual save.
 */
class WeightingSchemeItemRequest extends ResultsRequest
{
    private ?ResultWeightingScheme $scheme = null;

    public function authorize(): bool
    {
        return $this->scheme() !== null && ($this->user()?->hasPermission(Permission::ResultManage) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = $this->schoolId();
        $itemId = $this->route('item');

        return [
            'assessment_category_id' => [
                'required', 'integer',
                Rule::exists('assessment_categories', 'id')->where('school_id', $schoolId)->where('is_active', true),
                Rule::unique('result_weighting_scheme_items', 'assessment_category_id')
                    ->where('result_weighting_scheme_id', $this->scheme()?->id)
                    ->ignore($itemId),
            ],
            'weight_percentage' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $itemId = $this->route('item');
        if ($itemId !== null) {
            $item = ResultWeightingSchemeItem::query()->find($itemId);
            abort_if($item === null, 404);
            $this->scheme = $item->scheme;
        } else {
            $this->scheme = ResultWeightingScheme::query()->find($this->route('scheme'));
            abort_if($this->scheme === null, 404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'assessment_category_id' => $this->input('assessment_category_id'),
            'weight_percentage' => $this->input('weight_percentage'),
            'position' => (int) ($this->input('position') ?: 0),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assessment_category_id.exists' => __('The selected category is invalid.'),
            'assessment_category_id.unique' => __('That category is already weighted in this scheme.'),
        ];
    }

    public function scheme(): ?ResultWeightingScheme
    {
        return $this->scheme;
    }
}
