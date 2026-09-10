<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-scoped work is attempted with no active school context and
 * no explicit bypass. This is always a programming error — a route missing the
 * `tenant` middleware, or a console/queue routine that forgot to set a context
 * or wrap itself in `TenantContext::runWithoutScope()`.
 *
 * Failing loudly here is deliberate: the alternative (querying with no school
 * filter) would leak every school's data.
 */
class MissingTenantContextException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No school (tenant) context is active for this operation. Add the '
            .'`tenant` middleware to the route, set a context explicitly, or wrap '
            .'the call in TenantContext::runWithoutScope() if it is genuinely '
            .'platform-wide.'
        );
    }
}
