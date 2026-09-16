<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle one feature module on or off for the active school. The module itself
 * is a route segment (resolved and 404'd in the controller); this validates the
 * desired state. Dependency rules ("enable X first") are checked in the
 * controller, which has the school's current module states to hand.
 *
 * Platform-Admin only — module activation is platform-level configuration,
 * not a School Admin action, even though School Admin otherwise holds every
 * `school.settings.*` permission. See `SchoolModuleController`.
 */
class UpdateSchoolModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatformAdmin() ?? false;
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
