<?php

namespace App\Http\Requests\Communication;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Create / edit an announcement. `announcement.manage` only (Principal /
 * School Admin). `status` / `published_at` are never set here — publication
 * goes through `AnnouncementController::publish()` /
 * `Announcement::publish()`.
 */
class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::AnnouncementManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', new Enum(AnnouncementAudience::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'title' => $this->string('title')->trim()->value(),
            'body' => $this->string('body')->trim()->value(),
            'audience' => $this->input('audience'),
        ];
    }
}
