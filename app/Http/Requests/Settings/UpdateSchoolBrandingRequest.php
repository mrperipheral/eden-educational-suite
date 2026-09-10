<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * School branding — an optional logo and a brand colour used by the app shell
 * and, later, portals. The uploaded file is validated by MIME type (from
 * content, not extension), size and dimensions, and stored on a private disk.
 */
class UpdateSchoolBrandingRequest extends FormRequest
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
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', // KB
                'dimensions:max_width=1600,max_height=1600,min_width=48,min_height=48',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_color.regex' => __('Enter a colour as a 6-digit hex value, e.g. #1D4ED8.'),
            'logo.mimetypes' => __('The logo must be a JPEG, PNG or WebP image.'),
            'logo.dimensions' => __('The logo must be between 48px and 1600px on each side.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('brand_color')) {
            $this->merge(['brand_color' => mb_strtolower(trim((string) $this->input('brand_color')))]);
        }
    }
}
