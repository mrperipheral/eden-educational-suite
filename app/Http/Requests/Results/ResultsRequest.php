<?php

namespace App\Http\Requests\Results;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared helper for the Results & Report Cards Form Requests. Unlike M13/M14,
 * writes here are split across several distinct permissions (`result.manage`,
 * `.enter`, `.publish`, `.adjust`) rather than one coarse "record" ability, so
 * each concrete request declares its own `authorize()`.
 */
abstract class ResultsRequest extends FormRequest
{
    protected function schoolId(): int
    {
        return app(TenantContext::class)->idOrFail();
    }
}
