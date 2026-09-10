<?php

namespace App\Http\Requests\Academic;

use App\Enums\Permission;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every academic-configuration write request. Managing the academic
 * structure needs `academics.manage`; the route also carries `->can()` and the
 * `module:academics` gate.
 */
abstract class AcademicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::AcademicsManage) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }

    /**
     * Normalise a `code` field to trimmed upper-case before validation.
     */
    protected function normaliseCode(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
