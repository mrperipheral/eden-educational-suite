<?php

namespace App\Http\Requests\Student;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every student-module write request. Managing students needs
 * `student.manage`; the route also carries `->can()` and the `module:students`
 * gate.
 */
abstract class StudentModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::StudentManage) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
