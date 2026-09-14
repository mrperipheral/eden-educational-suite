<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * School branding — an optional logo, cover image, primary/accent colour and
 * motto used by the app shell and portals. Uploaded files are validated by
 * MIME type (from content, not extension), size and dimensions, and stored
 * on a private disk.
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
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'motto' => ['nullable', 'string', 'max:160'],
            'logo' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', // KB
                'dimensions:max_width=1600,max_height=1600,min_width=48,min_height=48',
            ],
            'cover' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096', // KB
                'dimensions:min_width=320,min_height=120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_color.regex' => __('Enter a colour as a 6-digit hex value, e.g. #1D4ED8.'),
            'accent_color.regex' => __('Enter a colour as a 6-digit hex value, e.g. #F59E0B.'),
            'logo.mimetypes' => __('The logo must be a JPEG, PNG or WebP image.'),
            'logo.dimensions' => __('The logo must be between 48px and 1600px on each side.'),
            'cover.mimetypes' => __('The cover image must be a JPEG, PNG or WebP image.'),
            'cover.dimensions' => __('The cover image must be at least 320×120px.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('brand_color')) {
            $this->merge(['brand_color' => mb_strtolower(trim((string) $this->input('brand_color')))]);
        }

        if ($this->filled('accent_color')) {
            $this->merge(['accent_color' => mb_strtolower(trim((string) $this->input('accent_color')))]);
        }
    }
}
