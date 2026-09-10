<?php

namespace App\Http\Requests\Academic;

use App\Models\AcademicLevel;
use App\Models\LevelArm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Create or edit an arm / stream. Name, code and order are unique *within the
 * level*.
 */
class ArmRequest extends AcademicRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        $levelId = $this->levelId();
        $armId = $this->route('arm');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('level_arms', 'name')->where('academic_level_id', $levelId)->ignore($armId),
            ],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 -]*$/',
                Rule::unique('level_arms', 'code')->where('academic_level_id', $levelId)->ignore($armId),
            ],
            'position' => [
                'required', 'integer', 'min:1', 'max:99',
                Rule::unique('level_arms', 'position')->where('academic_level_id', $levelId)->ignore($armId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseCode();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This level already has an arm with that name.'),
            'code.unique' => __('This level already has an arm with that code.'),
            'code.regex' => __('Use letters, numbers, spaces and hyphens only.'),
            'position.unique' => __('Another arm in this level already uses that order number.'),
        ];
    }

    /**
     * The level these rules scope to — resolved **tenant-scoped**, so a level id
     * from another school comes back null and the controller's `findOrFail`
     * makes it a 404 rather than a validation error.
     */
    private function levelId(): ?int
    {
        if ($this->route('level') !== null) {
            return AcademicLevel::query()->whereKey($this->route('level'))->value('id');
        }

        return LevelArm::query()->find($this->route('arm'))?->academic_level_id;
    }
}
