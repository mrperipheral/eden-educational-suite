<?php

namespace App\Http\Requests\Timetable;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every timetable-module write request. Managing timetables needs
 * `timetable.manage`; the route also carries `->can()` and the
 * `module:timetable` gate.
 */
abstract class TimetableModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::TimetableManage) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
