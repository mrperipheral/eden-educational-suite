<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every attendance-module write request. The coarse permission is
 * `attendance.record`; the route also carries `->can()` and the
 * `module:attendance` gate. Class-scoping (a teacher may only mark their
 * assigned classes) is added per request via `App\Support\Attendance\AttendanceAuthorizer`.
 */
abstract class AttendanceModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::AttendanceRecord) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
