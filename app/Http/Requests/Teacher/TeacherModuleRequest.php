<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every teacher-module write request. Managing teachers needs
 * `staff.manage`; the route also carries `->can()` and the `module:staff` gate.
 */
abstract class TeacherModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::StaffManage) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
