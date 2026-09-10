<?php

namespace App\Http\Requests\Guardian;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every guardian-module write request. Managing guardians needs
 * `guardian.manage`; the route also carries `->can()` and the `module:guardians`
 * gate.
 */
abstract class GuardianModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::GuardianManage) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
