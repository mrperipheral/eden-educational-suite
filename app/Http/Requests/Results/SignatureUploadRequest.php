<?php

namespace App\Http\Requests\Results;

use App\Enums\Permission;

/**
 * Upload a principal / class-teacher signature image for report cards.
 * Requires `result.manage`. Mirrors the validation
 * `App\Http\Requests\Settings\UpdateSchoolBrandingRequest` uses for the
 * school logo — validated by MIME type (from content, not extension), size
 * and dimensions.
 */
class SignatureUploadRequest extends ResultsRequest
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
        return [
            'signature' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048', // KB
                'dimensions:max_width=1600,max_height=1600,min_width=48,min_height=48',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature.mimetypes' => __('The signature must be a JPEG, PNG or WebP image.'),
            'signature.dimensions' => __('The signature must be between 48px and 1600px on each side.'),
        ];
    }
}
