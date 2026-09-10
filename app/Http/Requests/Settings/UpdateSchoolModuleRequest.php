<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle one feature module on or off for the active school. The module itself
 * is a route segment (resolved and 404'd in the controller); this validates the
 * desired state. Dependency rules ("enable X first") are checked in the
 * controller, which has the school's current module states to hand.
 */
class UpdateSchoolModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::SchoolSettingsUpdate) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }
}
