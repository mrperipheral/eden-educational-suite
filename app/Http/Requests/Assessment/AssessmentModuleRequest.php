<?php

namespace App\Http\Requests\Assessment;

use App\Enums\Permission;
use App\Support\Assessment\AssessmentAuthorizer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for assessment/assignment write requests that a recording teacher may
 * make. The coarse permission is `assessment.record`; the route also carries
 * `->can()` and the `module:assessments` gate. Class/subject-scoping (a teacher
 * only for a class + subject they are assigned to) is added per request via
 * {@see AssessmentAuthorizer}.
 */
abstract class AssessmentModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::AssessmentRecord) ?? false;
    }

    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
